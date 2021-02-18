import React, {FC} from 'react';
import {DependenciesProvider} from '@akeneo-pim-community/legacy-bridge';
import {ThemeProvider} from 'styled-components';
import {pimTheme} from 'akeneo-design-system';
import {SystemInfo} from "./SystemInfo";

const SystemInfoApp: FC = () => {
  return (
    <DependenciesProvider>
      <ThemeProvider theme={pimTheme}>
        <SystemInfo />
      </ThemeProvider>
    </DependenciesProvider>
  );
};

export {SystemInfoApp};
