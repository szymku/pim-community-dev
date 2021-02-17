import React from 'react';
import {Octocrumb} from './Octocrumb';
import {render, screen} from '../../storybook/test-util';

test('it renders its children properly', () => {
  render(<Octocrumb color={'green'}><Octocrumb.Step>First</Octocrumb.Step><Octocrumb.Step>Last</Octocrumb.Step></Octocrumb>);

  expect(screen.getByText('First')).toBeInTheDocument();
  expect(screen.getByText('Last')).toBeInTheDocument();
});

test('Octocrumb supports ...rest props', () => {
  render(<Octocrumb color={'green'} data-testid="my_value" />);
  expect(screen.getByTestId('my_value')).toBeInTheDocument();
});
