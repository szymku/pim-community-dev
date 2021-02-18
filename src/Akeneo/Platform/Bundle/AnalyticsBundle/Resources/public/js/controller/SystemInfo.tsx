import React, {FC} from "react";
import {PimView, useRoute, useTranslate} from "@akeneo-pim-community/legacy-bridge";
import {PageContent, PageHeader} from '@akeneo-pim-community/shared';
import {Breadcrumb, Table} from "akeneo-design-system";

const SystemInfo: FC = () => {
  const translate = useTranslate();
  const systemHomeRoute = useRoute('oro_config_configuration_system');

  const systemInfoData: any = {
    "pim_edition": "Serenity",
    "nb_channels": 3,
    "email_domains": "example.com",
    "api_connection": {
      "data_source": {
        "tracked": "2",
        "untracked": 0
      },
      "data_destination": {
        "tracked": "1",
        "untracked": 0
      },
    },
    "php_extensions": [
      "Core",
      "date",
      "xdebug"
    ]
  };

  const renderSystemInfo = (systemInfoType: string, systemInfoValue: any) => {
    return (
      <Table.Row key={systemInfoType}>
        <Table.Cell>
          {translate('pim_analytics.info_type.' + systemInfoType)}
        </Table.Cell>
        <Table.Cell>
          {renderInfoValue(systemInfoValue)}
        </Table.Cell>
      </Table.Row>
      );
  };

  const renderInfoValue = (infoValue: any) => {
    return Array.isArray(infoValue) ?
      infoValue.map((subInfo) => {return (
        <>
          {subInfo}
          <br/>
        </>
        );
      })
     : typeof infoValue === 'object' ? 'Object' : infoValue
  };

  return (
    <>
      <PageHeader>
        <PageHeader.Breadcrumb>
          <Breadcrumb>
            <Breadcrumb.Step href={`#${systemHomeRoute}`}>{translate('pim_menu.tab.system')}</Breadcrumb.Step>
            <Breadcrumb.Step>{translate('pim_analytics.system_info.title')}</Breadcrumb.Step>
          </Breadcrumb>
        </PageHeader.Breadcrumb>
        <PageHeader.UserActions>
          <PimView
            viewName="pim-menu-user-navigation"
            className="AknTitleContainer-userMenuContainer AknTitleContainer-userMenu"
          />
        </PageHeader.UserActions>
        <PageHeader.Title>
          {translate('pim_analytics.system_info.title')}
        </PageHeader.Title>
      </PageHeader>
      <PageContent>
        <Table>
          <Table.Header>
            <Table.HeaderCell>
              {translate('pim_analytics.info_header.name')}
            </Table.HeaderCell>
            <Table.HeaderCell>
              {translate('pim_analytics.info_header.value')}
            </Table.HeaderCell>
          </Table.Header>
          <Table.Body>
            {Object.keys(systemInfoData).map((systemInfoType: string) => {
              const systemInfoValue = systemInfoData[systemInfoType];
              return renderSystemInfo(systemInfoType, systemInfoValue);
            })}
          </Table.Body>
        </Table>
      </PageContent>
    </>
  );
}

export {SystemInfo};
