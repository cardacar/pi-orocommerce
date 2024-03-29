define(function(require) {
    'use strict';

    const _ = require('underscore');
    const $ = require('jquery');
    const __ = require('orotranslation/js/translator');
    const BaseView = require('oroui/js/app/views/base/view');
    const Confirmation = require('oroui/js/delete-confirmation');

    const AttributeFormOptionRowView = BaseView.extend({
        tagName: 'tr',

        events: {
            'click .delete-form-option': 'triggerRemove',
            'click .edit-form-option': 'triggerEdit'
        },

        options: {
            template: null,
            data: {
                label: null,
                property_path: null,
                required: false
            }
        },

        /**
         * @inheritdoc
         */
        constructor: function AttributeFormOptionRowView(options) {
            AttributeFormOptionRowView.__super__.constructor.call(this, options);
        },

        /**
         * @inheritdoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);
            const template = this.options.template || $('#attribute-form-option-row-template').html();
            this.template = _.template(template);
        },

        update: function(data) {
            this.options.data = data;
            this.render();
        },

        triggerEdit: function(e) {
            e.preventDefault();
            this.trigger('editFormOption', this.options.data);
        },

        triggerRemove: function(e) {
            e.preventDefault();

            const confirm = new Confirmation({
                content: __('Are you sure you want to delete this field?')
            });
            confirm.on('ok', _.bind(function() {
                this.trigger('removeFormOption', this.options.data);
            }, this));
            confirm.open();
        },

        getTemplateData: function() {
            return this.options.data;
        }
    });

    return AttributeFormOptionRowView;
});
