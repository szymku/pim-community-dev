import ReactDOM from 'react-dom';
import React from 'react';

const UserContext = require('pim/user-context');

class SystemInfo extends BaseDashboard {
  render() {
    ReactDOM.render(
      <div>
    test
      </div>,
      this.el
    );
  }
}

export = SystemInfo;
