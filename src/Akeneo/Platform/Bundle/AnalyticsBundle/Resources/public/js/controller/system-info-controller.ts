const BaseController = require('pim/controller/front');
const FormBuilder = require('pim/form-builder');

class SystemInfoController extends BaseController {
  public renderForm() {
    return $.when(FormBuilder.build('akeneo-analytics-system-info-content')).then((form: any, _ = []) => {
      form.setElement(this.$el).render();
      return form;
    });
  }
}

export = SystemInfoController;
