/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

define('field-plus:views/fields/id-card', 'views/fields/phone', function (Dep) {

    return Dep.extend({

        type: 'idCard',

        validations: ['required', 'phoneData'],

        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.name) || !this.model.get(this.name) === '') {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, 'div.id-card-block:nth-child(1) input');
                    return true;
                }
            }
        },

        data: function () {
            var idCardData;
            if (this.mode == 'edit') {
                idCardData = Espo.Utils.cloneDeep(this.model.get(this.dataFieldName));

                if (this.model.isNew() || !this.model.get(this.name)) {
                    if (!idCardData || !idCardData.length) {
                        var optOut = false;
                        if (this.model.isNew()) {
                            optOut = this.idCardOptedOutByDefault && this.model.name !== 'User';
                        } else {
                            optOut = this.model.get(this.isOptedOutFieldName)
                        }
                        idCardData = [{
                            idCard: this.model.get(this.name) || '',
                            primary: true,
                            type: this.defaultType,
                            optOut: optOut,
                            invalid: false
                        }];
                    }
                }
            } else {
                idCardData = this.model.get(this.dataFieldName) || false;
            }

            if (idCardData) {
                idCardData = Espo.Utils.cloneDeep(idCardData);
                idCardData.forEach(function (item) {
                    var number = item.idCard || '';
                    item.erased = number.indexOf(this.erasedPlaceholder) === 0;
                    if (!item.erased) {
                        item.valueForLink = number.replace(/ /g, '');
                    }
                    item.lineThrough = item.optOut || item.invalid || this.model.get('doNotCall');
                }, this);
            }

            if ((!idCardData || idCardData.length === 0) && this.model.get(this.name)) {
                var number = this.model.get(this.name);

                var o = {
                    idCard: number,
                    primary: true,
                    valueForLink: number.replace(/ /g, ''),
                };

                if (this.mode === 'edit' && this.model.isNew()) {
                    o.type = this.defaultType;
                }

                idCardData = [o];
            }

            var data = _.extend({
                idCardData: idCardData,
                doNotCall: this.model.get('doNotCall'),
                lineThrough: this.model.get('doNotCall') || this.model.get(this.isOptedOutFieldName)
            }, Dep.prototype.data.call(this));

            if (this.isReadMode()) {
                data.isOptedOut = this.model.get(this.isOptedOutFieldName);
                if (this.model.get(this.name)) {
                    data.isErased = this.model.get(this.name).indexOf(this.erasedPlaceholder) === 0;
                    if (!data.isErased) {
                        data.valueForLink = this.model.get(this.name).replace(/ /g, '');
                    }
                }
                data.valueIsSet = this.model.has(this.name);
            }

            data.itemMaxLength = this.itemMaxLength;

            return data;
        },

        events: {
            'click [data-action="switchIdCardProperty"]': function (e) {
                var $target = $(e.currentTarget);
                var $block = $(e.currentTarget).closest('div.id-card-block');
                var property = $target.data('property-type');


                if (property == 'primary') {
                    if (!$target.hasClass('active')) {
                        if ($block.find('input.id-card').val() != '') {
                            this.$el.find('button.phone-property[data-property-type="primary"]').removeClass('active').children().addClass('text-muted');
                            $target.addClass('active').children().removeClass('text-muted');
                        }
                    }
                } else {
                    if ($target.hasClass('active')) {
                        $target.removeClass('active').children().addClass('text-muted');
                    } else {
                        $target.addClass('active').children().removeClass('text-muted');
                    }
                }
                this.trigger('change');
            },

            'click [data-action="removeIdCard"]': function (e) {
                var $block = $(e.currentTarget).closest('div.id-card-block');
                if ($block.parent().children().length == 1) {
                    $block.find('input.id-card').val('');
                } else {
                    this.removeidCardBlock($block);
                }
                this.trigger('change');
            },

            'change input.id-card': function (e) {
                var $input = $(e.currentTarget);
                var $block = $input.closest('div.id-card-block');

                if ($input.val() == '') {
                    if ($block.parent().children().length == 1) {
                        $block.find('input.id-card').val('');
                    } else {
                        this.removeidCardBlock($block);
                    }
                }

                this.trigger('change');

                this.manageAddButton();
            },

            'keypress input.id-card': function (e) {
                this.manageAddButton();
            },

            'paste input.id-card': function (e) {
                setTimeout(function () {
                    this.manageAddButton();
                }.bind(this), 10);
            },

            'click [data-action="addIdCard"]': function () {
                var data = Espo.Utils.cloneDeep(this.fetchIdCardData());

                o = {
                    idCard: '',
                    primary: data.length ? false : true,
                    type: false,
                    optOut: this.emailAddressOptedOutByDefault,
                    invalid: false,
                };

                data.push(o);

                this.model.set(this.dataFieldName, data, {silent: true});
                this.render();
            },
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.manageButtonsVisibility();
            this.manageAddButton();
        },

        removeIdCardBlock: function ($block) {
            var changePrimary = false;
            if ($block.find('button[data-property-type="primary"]').hasClass('active')) {
                changePrimary = true;
            }
            $block.remove();

            if (changePrimary) {
                this.$el.find('button[data-property-type="primary"]').first().addClass('active').children().removeClass('text-muted');
            }

            this.manageButtonsVisibility();
            this.manageAddButton();
        },

        manageAddButton: function () {
            var $input = this.$el.find('input.id-card');
            c = 0;
            $input.each(function (i, input) {
                if (input.value != '') {
                    c++;
                }
            });

            if (c == $input.length) {
                this.$el.find('[data-action="addIdCard"]').removeClass('disabled').removeAttr('disabled');
            } else {
                this.$el.find('[data-action="addIdCard"]').addClass('disabled').attr('disabled', 'disabled');
            }
        },

        manageButtonsVisibility: function () {
            var $primary = this.$el.find('button[data-property-type="primary"]');
            var $remove = this.$el.find('button[data-action="removeIdCard"]');
            if ($primary.length > 1) {
                $primary.removeClass('hidden');
                $remove.removeClass('hidden');
            } else {
                $primary.addClass('hidden');
                $remove.addClass('hidden');
            }
        },

        setup: function () {
            this.dataFieldName = this.name + 'Data';
            this.defaultType = this.defaultType || this.getMetadata().get('entityDefs.' + this.model.name + '.fields.' + this.name + '.defaultType');

            this.isOptedOutFieldName = this.name + 'IsOptedOut';

            this.idCardOptedOutByDefault = this.getConfig().get('idCardIsOptedOutByDefault');

            if (this.model.has('doNotCall')) {
                this.listenTo(this.model, 'change:doNotCall', function (model, value, o) {
                    if (this.mode !== 'detail' && this.mode !== 'list') return;
                    if (!o.ui) return;
                    this.reRender();
                }, this);
            }

            this.erasedPlaceholder = 'ERASED:';

            this.itemMaxLength = this.getMetadata().get(['entityDefs', 'idCard', 'fields', 'name', 'maxLength']);
        },

        fetchIdCardData: function () {
            var data = [];

            var $list = this.$el.find('div.id-card-block');

            if ($list.length) {
                $list.each(function (i, d) {
                    var row = {};
                    var $d = $(d);
                    row.idCard = $d.find('input.id-card').val().trim();
                    if (row.idCard == '') {
                        return;
                    }
                    row.primary = $d.find('button[data-property-type="primary"]').hasClass('active');
                    row.type = $d.find('select[data-property-type="type"]').val();
                    row.optOut = $d.find('button[data-property-type="optOut"]').hasClass('active');
                    row.invalid = $d.find('button[data-property-type="invalid"]').hasClass('active');
                    data.push(row);
                }.bind(this));
            }

            return data;
        },

        fetch: function () {
            var data = {};

            var addressData = this.fetchIdCardData() || [];
            data[this.dataFieldName] = addressData;
            data[this.name] = null;
            data[this.isOptedOutFieldName] = false;

            var primaryIndex = 0;
            addressData.forEach(function (item, i) {
                if (item.primary) {
                    primaryIndex = i;
                    if (item.optOut) {
                        data[this.isOptedOutFieldName] = true;
                    }
                    return;
                }
            }, this);

            if (addressData.length && primaryIndex > 0) {
                var t = addressData[0];
                addressData[0] = addressData[primaryIndex];
                addressData[primaryIndex] = t;
            }

            if (addressData.length) {
                data[this.name] = addressData[0].idCard;
            } else {
                data[this.isOptedOutFieldName] = null;
            }

            return data;
        }

    });
});
