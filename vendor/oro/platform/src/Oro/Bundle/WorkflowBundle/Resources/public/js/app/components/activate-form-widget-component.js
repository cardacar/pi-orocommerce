define(function(require) {
    'use strict';

    const _ = require('underscore');
    const __ = require('orotranslation/js/translator');
    const BaseComponent = require('oroui/js/app/components/base/component');
    const mediator = require('oroui/js/mediator');
    const widgetManager = require('oroui/js/widget-manager');

    const DeactivateFormWidgetComponent = BaseComponent.extend({
        /**
         * @property {Object}
         */
        options: {
            _wid: '',
            success: false,
            deactivated: null,
            selectors: {
                form: null
            },
            buttonName: 'activate',
            error: null
        },

        /**
         * @inheritdoc
         */
        constructor: function DeactivateFormWidgetComponent(options) {
            DeactivateFormWidgetComponent.__super__.constructor.call(this, options);
        },

        /**
         * @inheritdoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);

            const self = this;

            widgetManager.getWidgetInstance(
                this.options._wid,
                function(widget) {
                    if (!self.options.success) {
                        if (self.options.error) {
                            mediator.execute('showMessage', 'error', self.options.error);
                        }

                        widget.getAction(self.options.buttonName, 'adopted', function(action) {
                            action.on('click', _.bind(self.onClick, self));
                        });
                    } else {
                        mediator.trigger('widget_success:' + widget.getAlias());
                        mediator.trigger('widget_success:' + widget.getWid());

                        let response = {message: __('oro.workflow.activated')};

                        if (!_.isEmpty(self.options.deactivated)) {
                            response = _.extend(response, {
                                deactivatedMessage: __('oro.workflow.deactivated_list') + self.options.deactivated
                            });
                        }

                        widget.trigger('formSave', response);
                        widget.remove();
                    }
                }
            );
        },

        onClick: function() {
            this.options._sourceElement.find(this.options.selectors.form).trigger('submit');
        }
    });

    return DeactivateFormWidgetComponent;
});
