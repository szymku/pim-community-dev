<?php
declare(strict_types=1);

namespace Specification\Akeneo\UserManagement\Component\Connector\Job;

use Akeneo\Tool\Component\Batch\Event\EventInterface;
use Akeneo\Tool\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Batch\Job\BatchStatus;
use Akeneo\Tool\Component\Batch\Job\ExitStatus;
use Akeneo\Tool\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Tool\Component\Batch\Job\JobStopper;
use Akeneo\Tool\Component\Batch\Model\StepExecution;
use Akeneo\Tool\Component\Batch\Step\StepInterface;
use Akeneo\Tool\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\UserManagement\Component\Connector\Job\ImportRoleTasklet;
use Akeneo\UserManagement\Component\Model\Role;
use Akeneo\UserManagement\Component\Repository\RoleRepositoryInterface;
use Oro\Bundle\SecurityBundle\Acl\Extension\AclExtensionInterface;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AclManager;
use Oro\Bundle\SecurityBundle\Metadata\ActionMetadata;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ImportRoleTaskletSpec extends ObjectBehavior
{
    function let(
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        ItemReaderInterface $reader,
        ItemWriterInterface $writer,
        JobStopper $jobStopper,
        RoleRepositoryInterface $roleRepository,
        ValidatorInterface $validator,
        ObjectDetacherInterface $objectDetacher,
        AclManager $aclManager,
        AclExtensionInterface $extension
    ) {
        $aclManager->getAllExtensions()->willReturn([$extension]);
        $extension->getExtensionKey()->willReturn('action');
        $extension->getClasses()->willReturn([
            new ActionMetadata('list_product'),
            new ActionMetadata('create_product'),
        ]);
        $extension->getAllMaskBuilders()->willReturn([]);

        $this->beConstructedWith(
            'name',
            $eventDispatcher,
            $jobRepository,
            $reader,
            $writer,
            $jobStopper,
            $roleRepository,
            $validator,
            $objectDetacher,
            $aclManager
        );
    }

    function it_is_a_step()
    {
        $this->shouldBeAnInstanceOf(ImportRoleTasklet::class);
        $this->shouldImplement(StepInterface::class);
    }

    function it_creates_a_role_and_updates_another_one_successfully(
        ItemReaderInterface $reader,
        ItemWriterInterface $writer,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        StepExecution $execution,
        BatchStatus $status,
        ExitStatus $exitStatus,
        JobStopper $jobStopper,
        RoleRepositoryInterface $roleRepository,
        ValidatorInterface $validator,
        AclManager $aclManager,
        ObjectDetacherInterface $objectDetacher
    ) {
        $execution->getStatus()->willReturn($status);
        $status->getValue()->willReturn(BatchStatus::STARTING);

        $eventDispatcher->dispatch(Argument::any(), EventInterface::BEFORE_STEP_EXECUTION)->shouldBeCalled();
        $execution->setStartTime(Argument::any())->shouldBeCalled();
        $execution->setStatus(Argument::any())->shouldBeCalled();

        $reader->read()->willReturn(
            [
                'label' => 'Admin',
                'permissions' => [
                    [
                        'id' => 'action:list_product',
                        'permissions' => [
                            'EXECUTE' => ['name' => 'EXECUTE', 'access_level' => 1],
                        ],
                    ],
                    [
                        'id' => 'action:create_product',
                        'permissions' => [
                            'EXECUTE' => ['name' => 'EXECUTE', 'access_level' => 1],
                        ],
                    ],
                    [
                        'id' => 'action:unknown',
                        'permissions' => [
                            'EXECUTE' => ['name' => 'EXECUTE', 'access_level' => 1],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'User',
                'permissions' => [
                    [
                        'id' => 'action:list_product',
                        'permissions' => [
                            'EXECUTE' => ['name' => 'EXECUTE', 'access_level' => 1],
                        ],
                    ],
                    [
                        'id' => 'action:create_product',
                        'permissions' => [
                            'EXECUTE' => ['name' => 'EXECUTE', 'access_level' => 0],
                        ],
                    ],
                ],
            ],
            null
        );

        $adminRole = new Role('ROLE_ADMIN');
        $adminRole->setLabel('Admin');
        $roleRepository->findOneByLabel('Admin')->willReturn($adminRole);
        $validator->validate($adminRole)->willReturn(new ConstraintViolationList([]));
        $writer->write([$adminRole])->shouldBeCalled();
        $objectDetacher->detach($adminRole)->shouldBeCalled();

        $userRole = new Role('ROLE_USER');
        $userRole->setLabel('User');
        $roleRepository->findOneByLabel('User')->willReturn(null);
        $roleRepository->findOneByIdentifier('ROLE_USER')->willReturn(null);
        $validator->validate($userRole)->willReturn(new ConstraintViolationList([]));
        $writer->write([$userRole])->shouldBeCalled();
        $objectDetacher->detach($userRole)->shouldBeCalled();

        $this->permissionsShouldBeSet($aclManager, $adminRole, $userRole);

        $execution->incrementProcessedItems(1)->shouldBeCalledTimes(2);

        $jobStopper->isStopping($execution)->willReturn(false);

        $execution->getExitStatus()->willReturn($exitStatus);
        $exitStatus->getExitCode()->willReturn(ExitStatus::COMPLETED);
        $jobRepository->updateStepExecution($execution)->shouldBeCalledTimes(5);
        $execution->isTerminateOnly()->willReturn(false);

        $execution->upgradeStatus(Argument::any())->shouldBeCalled();
        $eventDispatcher->dispatch(Argument::any(), EventInterface::STEP_EXECUTION_SUCCEEDED)->shouldBeCalled();
        $eventDispatcher->dispatch(Argument::any(), EventInterface::STEP_EXECUTION_COMPLETED)->shouldBeCalled();
        $execution->setEndTime(Argument::any())->shouldBeCalled();
        $execution->setExitStatus(Argument::any())->shouldBeCalled();

        $this->execute($execution);
    }

    function it_dispatches_an_event_when_role_is_not_valid(
        ItemReaderInterface $reader,
        ItemWriterInterface $writer,
        EventDispatcherInterface $eventDispatcher,
        JobRepositoryInterface $jobRepository,
        StepExecution $execution,
        BatchStatus $status,
        ExitStatus $exitStatus,
        JobStopper $jobStopper,
        RoleRepositoryInterface $roleRepository,
        ValidatorInterface $validator,
        AclManager $aclManager,
        ObjectDetacherInterface $objectDetacher
    ) {
        $execution->getStatus()->willReturn($status);
        $status->getValue()->willReturn(BatchStatus::STARTING);

        $eventDispatcher->dispatch(Argument::any(), EventInterface::BEFORE_STEP_EXECUTION)->shouldBeCalled();
        $execution->setStartTime(Argument::any())->shouldBeCalled();
        $execution->setStatus(Argument::any())->shouldBeCalled();

        $reader->read()->willReturn(
            ['label' => 'Admin', 'permissions' => []],
            null
        );

        $adminRole = new Role('ROLE_ADMIN');
        $adminRole->setLabel('Admin');
        $roleRepository->findOneByLabel('Admin')->willReturn($adminRole);
        $validator->validate($adminRole)->willReturn(new ConstraintViolationList([
            new ConstraintViolation('error_message', null, [], null, null, null)
        ]));
        $writer->write([$adminRole])->shouldNotBeCalled();
        $objectDetacher->detach($adminRole)->shouldBeCalled();

        $execution->getSummaryInfo('item_position')->willReturn(1);
        $jobRepository->addWarning(Argument::any())->shouldBeCalled();
        $eventDispatcher->dispatch(Argument::any(), EventInterface::INVALID_ITEM)->shouldBeCalled();


        $execution->incrementProcessedItems(1)->shouldBeCalledOnce();

        $jobStopper->isStopping($execution)->willReturn(false);

        $execution->getExitStatus()->willReturn($exitStatus);
        $exitStatus->getExitCode()->willReturn(ExitStatus::COMPLETED);
        $jobRepository->updateStepExecution($execution)->shouldBeCalledTimes(4);
        $execution->isTerminateOnly()->willReturn(false);

        $execution->upgradeStatus(Argument::any())->shouldBeCalled();
        $eventDispatcher->dispatch(Argument::any(), EventInterface::STEP_EXECUTION_SUCCEEDED)->shouldBeCalled();
        $eventDispatcher->dispatch(Argument::any(), EventInterface::STEP_EXECUTION_COMPLETED)->shouldBeCalled();
        $execution->setEndTime(Argument::any())->shouldBeCalled();
        $execution->setExitStatus(Argument::any())->shouldBeCalled();

        $this->execute($execution);
    }

    private function permissionsShouldBeSet(AclManager $aclManager, Role $adminRole, Role $userRole): void
    {
        $createProductOid = new ObjectIdentity('action', 'create_product');
        $listProductOid = new ObjectIdentity('action', 'list_product');
        $aclManager->getRootOid('action')->WillReturn(new ObjectIdentity('id', 'type'));

        $adminSid = new RoleSecurityIdentity($adminRole->getRole());
        $aclManager->getSid($adminRole)->willReturn($adminSid);
        $aclManager->setPermission($adminSid, $listProductOid, 1, true)->shouldBeCalled();
        $aclManager->setPermission($adminSid, $createProductOid, 1, true)->shouldBeCalled();
        $aclManager->flush()->shouldBeCalled();

        $userSid = new RoleSecurityIdentity($userRole->getRole());
        $aclManager->getSid($userRole)->willReturn($userSid);
        $aclManager->setPermission($userSid, $listProductOid, 1, true)->shouldBeCalled();
        $aclManager->setPermission($userSid, $createProductOid, 0, true)->shouldBeCalled();
        $aclManager->flush()->shouldBeCalled();
    }
}
