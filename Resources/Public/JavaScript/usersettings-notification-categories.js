import DocumentService from '@typo3/core/document-service.js';

/**
* Keeps the hidden "notify categories" field in sync with the checkbox tree
* rendered by NotificationCategoryField in the User Settings module. The hidden
* field holds a comma-separated list of selected sys_category UIDs, which the
* SetupModuleController persists to the be_users category relation.
*/
class UserSettingsNotificationCategories {
  constructor() {
    DocumentService.ready().then(() => this.init());
  }

  init() {
    document.querySelectorAll('[data-notify-categories]').forEach((widget) => {
      const hidden = widget.querySelector('[data-notify-categories-value]');
      if (!hidden) {
        return;
      }

      const sync = () => {
        const ids = Array.from(widget.querySelectorAll('input[type="checkbox"]:checked'))
          .map((checkbox) => checkbox.value);
        hidden.value = ids.join(',');
      };

      widget.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
        checkbox.addEventListener('change', sync);
      });
    });
  }
}

new UserSettingsNotificationCategories();
