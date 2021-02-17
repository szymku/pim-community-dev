import React, {ReactNode} from 'react';
import styled from 'styled-components';
import {AkeneoThemedProps, getColor, getFontSize} from '../../theme';

const OctocrumbContainer = styled.nav<{color: string}>``;

type StepProps = {
  color: string;
  gradient: number;
};

const Step = styled.a<StepProps & AkeneoThemedProps>`
  color: ${({color, gradient}) => getColor(color, gradient)};
  text-transform: uppercase;
  text-decoration: none;
  font-size: ${getFontSize('default')};
`;

const Separator = styled.span`
  color: ${getColor('grey', 100)};
  font-size: ${getFontSize('default')};
  :after {
    content: '/';
    margin: 0 0.5rem;
  }
`;

type OctocrumbProps = {
  /**
   * The color of ze breadcrumb.
   */
  color: 'green' | 'yellow' | 'red';

  /**
   * TODO.
   */
  children?: ReactNode;
};

/**
 * Octocrumbs are an important navigation component that shows content hierarchy.
 */
const Octocrumb = ({color = 'green', children, ...rest}: OctocrumbProps) => {
  const childrenCount = React.Children.count(children);

  const decoratedChildren = React.Children.map(children, (child, index) => {
    if (!(React.isValidElement(child) && child.type === Step)) {
      throw new Error('only Step are accepted in Octocrumb');
    }

    return index === childrenCount - 1 ? (
      React.cloneElement(child, {color, gradient: 100, ariaCurrent: 'page'})
    ) : (
      <>
        {React.cloneElement(child, {color, gradient: 120})}
        <Separator aria-hidden={true} />
      </>
    );
  });

  return (
    <OctocrumbContainer color={color} aria-label="Breadcrumb" {...rest}>
      {decoratedChildren}
    </OctocrumbContainer>
  );
};

Octocrumb.Step = Step;
Step.displayName = 'Octocrumb.Step';

export {Octocrumb};
