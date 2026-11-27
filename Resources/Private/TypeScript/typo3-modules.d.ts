declare module '@typo3/core/ajax/ajax-request.js' {
    interface AjaxResponse {
        resolve<T = unknown>(): Promise<T>;
    }

    export default class AjaxRequest {
        constructor(url: string | URL);
        withQueryArguments(data: string | Record<string, string>): AjaxRequest;
        abort(): void;
        get(init?: RequestInit): Promise<AjaxResponse>;
        post(payload?: unknown, init?: RequestInit): Promise<AjaxResponse>;
    }
}

declare module '@typo3/core/document-service.js' {
    export default class DocumentService {
        static ready(): Promise<Document>;
    }
}

declare module '@typo3/backend/notification.js' {
    export default class Notification {
        static error(title: string, message?: string): void;
    }
}

declare module '@typo3/backend/viewport.js' {
    export default class Viewport {
        static readonly ContentContainer: {
            setUrl(url: string): void;
        };
    }
}

declare module '@typo3/backend/modal.js' {
    type ModalButton = {
        text: string;
        btnClass: string;
        name?: string;
    };

    type ModalOptions = {
        title: string;
        content: unknown;
        size?: 'small' | 'default' | 'medium' | 'large' | 'full';
        additionalCssClasses?: string[];
        buttons?: ModalButton[];
    };

    interface ModalElement extends HTMLElement {
        hideModal(): void;
    }

    export default class Modal {
        static advanced(options: ModalOptions): ModalElement;
    }
}
