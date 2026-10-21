/**
* TYPO3 provides these modules at runtime through its JavaScript import map.
* These declarations provide the local TypeScript/IDE information without
* adding TYPO3 runtime packages or Lit as npm dependencies.
*/
declare module '@typo3/core/ajax/ajax-request.js' {
  interface AjaxResponse {
    resolve(): Promise<unknown>;
  }

  export default class AjaxRequest {
    constructor(url: string | URL);
    get(init?: RequestInit): Promise<AjaxResponse>;
    post(payload?: unknown, init?: RequestInit): Promise<AjaxResponse>;
  }
}

declare module '@typo3/*';

declare module 'lit/decorators.js';

declare module 'lit' {
  export class LitElement extends HTMLElement {
    render(): unknown;
    createRenderRoot(): HTMLElement | DocumentFragment;
  }

  export function html(
    strings: TemplateStringsArray,
    ...values: unknown[]
  ): unknown;

  export const nothing: unique symbol;
}
