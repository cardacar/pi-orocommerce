define(function(require) {
    'use strict';

    const _ = require('underscore');
    const mediator = require('oroui/js/mediator');
    const BaseComponent = require('oroui/js/app/components/base/component');
    const LoadingMaskView = require('oroui/js/app/views/loading-mask-view');
    const NumberFormatter = require('orolocale/js/formatter/number');

    /**
     * @export orotax/js/app/components/order-line-item-discounts-tax-component
     * @extends oroui.app.components.base.Component
     * @class orotax.app.components.OrderLineItemAppliedDiscountsComponent
     */
    const OrderLineItemAppliedDiscountsComponent = BaseComponent.extend({
        /**
         * @property {Object}
         */
        options: {
            selectors: {
                rowTotalAfterDiscountIncludingTax: '.applied-discount-row-total-after-discount-including-tax',
                rowTotalAfterDiscountExcludingTax: '.applied-discount-row-total-after-discount-excluding-tax',
                appliedDiscountsAmount: '.applied-discount-row-total-discount-amount'
            }
        },

        /**
         * @property {LoadingMaskView}
         */
        loadingMaskView: null,

        /**
         * @inheritdoc
         */
        constructor: function OrderLineItemAppliedDiscountsComponent(options) {
            OrderLineItemAppliedDiscountsComponent.__super__.constructor.call(this, options);
        },

        /**
         * @inheritdoc
         */
        initialize: function(options) {
            this.options = _.defaults(options || {}, this.options);

            mediator.on('entry-point:order:load:before', this.showLoadingMask, this);
            mediator.on('entry-point:order:load', this.setDiscounts, this);
            mediator.on('entry-point:order:load:after', this.hideLoadingMask, this);

            this.loadingMaskView = new LoadingMaskView({container: this.options._sourceElement});
        },

        showLoadingMask: function() {
            this.loadingMaskView.show();
        },

        hideLoadingMask: function() {
            this.loadingMaskView.hide();
        },

        /**
         * @param {Object} response
         */
        setDiscounts: function(response) {
            const itemId = this.options._sourceElement.closest('tr.order-line-item').index();
            if (!_.has(response.appliedDiscounts, itemId)) {
                return;
            }
            const discounts = response.appliedDiscounts[itemId];
            const appliedDiscountsAmount = NumberFormatter.formatCurrency(
                discounts.appliedDiscountsAmount,
                discounts.currency
            );
            const rowTotalAfterDiscountExcludingTax = NumberFormatter.formatCurrency(
                discounts.rowTotalAfterDiscountExcludingTax,
                discounts.currency
            );
            const rowTotalAfterDiscountIncludingTax = NumberFormatter.formatCurrency(
                discounts.rowTotalAfterDiscountIncludingTax,
                discounts.currency
            );
            this.options._sourceElement
                .find(this.options.selectors.appliedDiscountsAmount)
                .text(appliedDiscountsAmount);
            this.options._sourceElement
                .find(this.options.selectors.rowTotalAfterDiscountExcludingTax)
                .text(rowTotalAfterDiscountExcludingTax);
            this.options._sourceElement
                .find(this.options.selectors.rowTotalAfterDiscountIncludingTax)
                .text(rowTotalAfterDiscountIncludingTax);
        },

        /**
         * @inheritdoc
         */
        dispose: function() {
            if (this.disposed) {
                return;
            }

            mediator.off('entry-point:order:load:before', this.showLoadingMask, this);
            mediator.off('entry-point:order:load', this.setDiscounts, this);
            mediator.off('entry-point:order:load:after', this.hideLoadingMask, this);

            OrderLineItemAppliedDiscountsComponent.__super__.dispose.call(this);
        }
    });

    return OrderLineItemAppliedDiscountsComponent;
});
