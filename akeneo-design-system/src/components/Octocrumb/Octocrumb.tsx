import React, {Ref, ReactNode} from 'react';
import styled from 'styled-components';
import {AkeneoThemedProps, getColor, getFontSize} from "../../theme";

//TODO be sure to select the appropriate container element here
const OctocrumbContainer = styled.div<{color: string}>``;
const Step = styled.a<{color: string, gradient: number} & AkeneoThemedProps>`
  color: ${({color, gradient}) => getColor(color, gradient)};
  text-transform: uppercase;
  text-decoration: none;
  font-size:${getFontSize('default')};
`;
const Separator = styled.span`
  color: ${getColor('grey', 100)};
  font-size:${getFontSize('default')};
  :after {
    content: '/';
    margin: 0 0.5rem;
  }
`;

type OctocrumbProps = {
  /**
   * The color of ze breadcrumb.
   */
  color?: 'green' | 'yellow' | 'red';

  /**
   * TODO.
   */
  children?: ReactNode;
};

/**
 * TODO.
 */
const Octocrumb = React.forwardRef<HTMLDivElement, OctocrumbProps>(
  ({color = 'green', children, ...rest}: OctocrumbProps, forwardedRef: Ref<HTMLDivElement>) => {
    return (
      <OctocrumbContainer color={color} ref={forwardedRef} {...rest}>
        {children}
      </OctocrumbContainer>
    );
  }
);

export {Octocrumb, Step, Separator};
