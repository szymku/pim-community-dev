<?php
declare(strict_types=1);

namespace Akeneo\UserManagement\Component\Connector\Job;

use Akeneo\Tool\Component\Batch\Item\FileInvalidItem;
use Akeneo\Tool\Component\Batch\Item\FlushableInterface;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\InvalidItemException;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Batch\Item\TrackableItemReaderInterface;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Model\Warning;
use Akeneo\Tool\Component\Batch\Step\AbstractStep;
use Akeneo\Tool\Component\Batch\Step\StepExecutionAwareInterface;
use Akeneo\Tool\Component\Batch\Step\StoppableStepInterface;
use Akeneo\Tool\Component\Batch\Step\TrackableStepInterface;
use Akeneo\Tool\Component\Connector\Exception\InvalidItemFromViolationsException;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\UserManagement\Component\Model\Role;
use Akeneo\UserManagement\Component\Model\RoleInterface;
use Akeneo\UserManagement\Component\Model\User;
use Akeneo\UserManagement\Component\Repository\RoleRepositoryInterface;
use Oro\Bundle\SecurityBundle\Acl\AccessLevel;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AclManager;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Webmozart\Assert\Assert;

/**
 * This tasklet is used during job import to handle:
 *  - string role creation (e.g. "ROLE_USER")
 *  - add/remove permissions
 *
 * During a catalog install it's another step that creates the entire Role.
 *
 * @author    Nicolas Marniesse <nicolas.marniesse@akeneo.com>
 * @copyright 2021 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
final class ImportRoleTasklet extends AbstractStep implements TrackableStepInterface, LoggerAwareInterface, StoppableStepInterface
{
    private const ACL_EXTENSION_KEY = 'action';
    private const MAX_ATTEMPTS_TO_CREATE_A_ROLE = 100;

    use LoggerAwareTrait;

    protected ItemReaderInterface $reader;
    protected ItemWriterInterface $writer;
    private bool $stoppable = false;
    private JobStopper $jobStopper;
    private RoleRepositoryInterface $roleRepository;
    private ValidatorInterface $validator;
    private ObjectDetacherInterface $objectDetacher;
    private AclManager $aclManager;
    protected ?StepExecution $stepExecution = null;

    public function __construct(
        string $name,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        ItemReaderInterface $reader,
        ItemWriterInterface $writer,
        JobStopper $jobStopper,
        RoleRepositoryInterface $roleRepository,
        ValidatorInterface $validator,
        ObjectDetacherInterface $objectDetacher,
        AclManager $aclManager
    ) {
        parent::__construct($name, $eventDispatcher, $jobRepository);

        $this->reader = $reader;
        $this->writer = $writer;
        $this->jobStopper = $jobStopper;
        $this->roleRepository = $roleRepository;
        $this->validator = $validator;
        $this->objectDetacher = $objectDetacher;
        $this->aclManager = $aclManager;
    }

    public function isTrackable(): bool
    {
        return $this->reader instanceof TrackableItemReaderInterface;
    }

    public function setStoppable(bool $stoppable): void
    {
        $this->stoppable = $stoppable;
    }

    /**
     * {@inheritDoc}
     */
    protected function doExecute(StepExecution $stepExecution)
    {
        $this->initializeStepElements($stepExecution);
        Assert::notNull($this->stepExecution);
        if ($this->isTrackable()) {
            $stepExecution->setTotalItems($this->getCountFromTrackableItemReader());
        }

        while (true) {
            try {
                $readItem = $this->reader->read();
                if (null === $readItem) {
                    break;
                }
            } catch (InvalidItemException $e) {
                $this->handleStepExecutionWarning($this->stepExecution, $this->reader, $e);
                $this->updateProcessedItems();

                continue;
            }

            $role = $this->fetchOrCreate($readItem);
            if (null === $role) {
                continue;
            }

            if ($this->validate($role, $readItem)) {
                $this->write($role);
                $this->updatePermissions($role, $readItem['permissions']);
            }

            $this->objectDetacher->detach($role);
            $this->updateProcessedItems();
            if ($this->jobStopper->isStopping($stepExecution)) {
                $this->jobStopper->stop($stepExecution);

                break;
            }
        }
    }

    private function fetchOrCreate(array $readItem): Role
    {
        Assert::stringNotEmpty($readItem['label'] ?? null);
        $roleLabel = $readItem['label'];
        $role = $this->roleRepository->findOneByLabel($roleLabel);
        if (null === $role) {
            $role = new Role();
            $role->setLabel($roleLabel);

            $attempts = 0;
            while ($attempts < static::MAX_ATTEMPTS_TO_CREATE_A_ROLE) {
                $role->setRole(0 === $attempts ? $roleLabel : ($roleLabel . $attempts));
                $identifier = $role->getRole();
                if (null === $this->roleRepository->findOneByIdentifier($identifier)) {
                    break;
                }

                $attempts++;
            }

            if ($attempts === static::MAX_ATTEMPTS_TO_CREATE_A_ROLE) {
                $itemPosition = $this->stepExecution->getSummaryInfo('item_position');
                $this->handleStepExecutionWarning(
                    $this->stepExecution,
                    $this,
                    new InvalidItemException(
                        'Cannot create role: too many roles have a similar label.',
                        new FileInvalidItem($readItem, $itemPosition),
                    )
                );
                $this->updateProcessedItems();
            }
        }

        return $role;
    }

    private function validate(Role $role, array $readItem): bool
    {
        $violations = $this->validator->validate($role);
        if (0 === $violations->count()) {
            return true;
        }

        $itemPosition = $this->stepExecution->getSummaryInfo('item_position');
        $this->handleStepExecutionWarning(
            $this->stepExecution,
            $this,
            new InvalidItemFromViolationsException($violations, new FileInvalidItem($readItem, $itemPosition))
        );

        return false;
    }

    private function getCountFromTrackableItemReader(): int
    {
        if (!$this->reader instanceof TrackableItemReaderInterface) {
            throw new \RuntimeException('The reader should implement TrackableItemReaderInterface');
        }

        try {
            return $this->reader->totalItems();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical('Impossible to get the total items to process from the reader.');
            }
        }

        return 0;
    }

    /**
     * Handle step execution warning
     */
    protected function handleStepExecutionWarning(
        StepExecution $stepExecution,
        $element,
        InvalidItemException $e
    ) {
        $warning = new Warning(
            $stepExecution,
            $e->getMessage(),
            $e->getMessageParameters(),
            $e->getItem()->getInvalidData()
        );

        $this->jobRepository->addWarning($warning);

        $this->dispatchInvalidItemEvent(
            get_class($element),
            $e->getMessage(),
            $e->getMessageParameters(),
            $e->getItem()
        );
    }

    private function updateProcessedItems(int $processedItemsCount = 1): void
    {
        $this->stepExecution->incrementProcessedItems($processedItemsCount);
        $this->jobRepository->updateStepExecution($this->stepExecution);
    }

    protected function write(Role $role): void
    {
        try {
            $this->writer->write([$role]);
        } catch (InvalidItemException $e) {
            $this->handleStepExecutionWarning($this->stepExecution, $this->writer, $e);
        }
    }

    /**
     * Get the configurable step elements
     */
    protected function getStepElements(): array
    {
        return [
            'reader' => $this->reader,
            'writer' => $this->writer,
        ];
    }

    protected function initializeStepElements(StepExecution $stepExecution): void
    {
        $this->stepExecution = $stepExecution;
        foreach ($this->getStepElements() as $element) {
            if ($element instanceof StepExecutionAwareInterface) {
                $element->setStepExecution($stepExecution);
            }
            if ($element instanceof InitializableInterface) {
                $element->initialize();
            }
        }
    }

    /**
     * Flushes step elements
     */
    public function flushStepElements(): void
    {
        foreach ($this->getStepElements() as $element) {
            if ($element instanceof FlushableInterface) {
                $element->flush();
            }
        }
    }

    /**
     * Load the ACL per role
     *
     * @param RoleInterface $role
     */
    protected function updatePermissions(RoleInterface $role, array $privileges)
    {
        if (User::ROLE_ANONYMOUS === $role->getRole()) {
            return;
        }

        $indexedPermissionNames = [];
        foreach ($privileges as $privilege) {
            foreach ($privilege['permissions'] as $permission) {
                if ($permission['access_level'] !== AccessLevel::NONE_LEVEL) {
                    $name = $privilege['id'];
                    if (false !== strpos($name, ':')) {
                        $name = substr($name, 1 + strpos($name, ':'));
                    }

                    $indexedPermissionNames[$name] = 1;

                    break;
                }
            }
        }

        $sid = $this->aclManager->getSid($role);

        foreach ($this->aclManager->getAllExtensions() as $extension) {
            if (static::ACL_EXTENSION_KEY !== $extension->getExtensionKey()) {
                continue;
            }

            $rootOid = $this->aclManager->getRootOid($extension->getExtensionKey());
            foreach ($extension->getAllMaskBuilders() as $maskBuilder) {
                $fullAccessMask = $maskBuilder->hasConst('GROUP_SYSTEM')
                    ? $maskBuilder->getConst('GROUP_SYSTEM')
                    : $maskBuilder->getConst('GROUP_ALL');
                $this->aclManager->setPermission($sid, $rootOid, $fullAccessMask, true);
            }

            foreach ($extension->getClasses() as $aclClassInfo) {
                $mask = array_key_exists($aclClassInfo->getClassName(), $indexedPermissionNames)
                    ? AccessLevel::BASIC_LEVEL
                    : AccessLevel::NONE_LEVEL
                ;
                $oid = new ObjectIdentity($extension->getExtensionKey(), $aclClassInfo->getClassName());
                $this->aclManager->setPermission($sid, $oid, $mask, true);
            }
        }

        $this->aclManager->flush();
    }
}
