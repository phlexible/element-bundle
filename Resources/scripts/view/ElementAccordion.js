Ext.provide('Phlexible.elements.ElementAccordion');

Ext.require('Phlexible.elements.accordion.AllowedChildren');
Ext.require('Phlexible.elements.accordion.Comment');
Ext.require('Phlexible.elements.accordion.Configuration');
Ext.require('Phlexible.elements.accordion.Data');
Ext.require('Phlexible.elements.accordion.Diff');
Ext.require('Phlexible.elements.accordion.Instances');
Ext.require('Phlexible.elements.accordion.Meta');
Ext.require('Phlexible.elements.accordion.Versions');

Phlexible.elements.ElementAccordion = Ext.extend(Ext.Panel, {
//    title: Phlexible.elements.Strings.properties,
//    header: true,
    cls: 'p-elements-accordion',

    //autoScroll: true,
    layout: 'accordion',
    layoutConfig: {
        titleCollapse: true,
        fill: true
        //activeOnTop: true
    },

    initComponent: function () {
        this.element.on({
            load: this.onLoadElement,
            getlock: this.onGetLock,
            islocked: this.onIsLocked,
            removelock: this.onRemoveLock,
            scope: this
        });

        this.populateItems();

        Phlexible.elements.ElementAccordion.superclass.initComponent.call(this);
    },

    populateItems: function () {
        this.items = [
            {
                xtype: 'elements-dataaccordion',
                collapsed: true
            }
        ];

        if (Phlexible.User.isGranted('ROLE_ELEMENT_CONFIG')) {
            this.items.push({
                xtype: 'elements-configurationaccordion',
                collapsed: true
            });
        }

        if (Phlexible.User.isGranted('ROLE_ELEMENT_META')) {
            this.items.push({
                xtype: 'elements-metaaccordion',
                collapsed: true
            });
        }

        if (Phlexible.User.isGranted('ROLE_ELEMENT_CHILDREN')) {
            this.items.push({
                xtype: 'elements-allowedchildrenaccordion',
                collapsed: true,
                listeners: {
                    rowdblclick: function (grid, index) {
                        if (this.element.data.rights.indexOf('CREATE') === -1) return;

                        var r = grid.store.getAt(index);
                        var et_id = r.id;
                        this.element.showNewElementWindow(false, et_id);
                    },
                    scope: this
                }
            });
        }

        if (Phlexible.User.isGranted('ROLE_ELEMENT_VERSIONS')) {
            this.items.push({
                xtype: 'elements-versionsaccordion',
                collapsed: true,
                listeners: {
                    loadVersion: function (version) {
                        this.element.reload({
                            version: version
                        });
                    },
                    scope: this
                }
            });
        }

        if (Phlexible.User.isGranted('ROLE_ELEMENT_INSTANCES')) {
            this.items.push({
                xtype: 'elements-instancesaccordion',
                collapsed: true,
                listeners: {
                    loadElement: function (id) {
                        this.element.reload({
                            id: id
                        });
                    },
                    loadTeaser: function (id) {
                        this.element.reload({
                            teaser_id: id
                        });
                    },
                    scope: this
                }
            });
        }

        if (Phlexible.User.isGranted('ROLE_ELEMENT_COMMENT')) {
            this.items.push({
                xtype: 'elements-commentaccordion',
                collapsed: true
            });
        }

        //},{
        //    xtype: 'elements-diffaccordion',
        //    collapsed: true,
        //    element: this.element
    },

    onLoadElement: function (element) {
        this.eid = element.eid;
        this.version = element.version;
        this.language = element.language;

        this.items.each(function (acc) {
            acc.load(element.data, element);
        });
    },

    getData: function () {
        var data = {};

        this.items.each(function (acc) {
            if (!acc.hidden && !acc.disabled && acc.key && acc.getData && typeof acc.getData === 'function') {
                data[acc.key] = acc.getData();
            }
        });

        return data;
    },

    isValid: function () {
        var valid = true;

        this.items.each(function (acc) {
            if (!acc.hidden && !acc.disabled && acc.key && acc.isValid && typeof acc.isValid === 'function') {
                valid &= !!acc.isValid();
            }
        });

        return valid;
    },

    saveData: function () {
        this.items.each(function (acc) {
            if (!acc.hidden && !acc.disabled && acc.saveData && typeof acc.saveData === 'function') {
                alert('acc.saveData');
                acc.saveData();
            }
        });
    },

    onGetLock: function () {
        this.items.each(function (acc) {
            if ((acc.getData && typeof acc.getData === 'function') || (acc.saveData && typeof acc.saveData === 'function')) {
                acc.enable();
            }
        });
    },


    onIsLocked: function () {
        this.items.each(function (acc) {
            if ((acc.getData && typeof acc.getData === 'function') || (acc.saveData && typeof acc.saveData === 'function')) {
                acc.disable();
            }
        });
    },


    onRemoveLock: function () {
        this.items.each(function (acc) {
            if ((acc.getData && typeof acc.getData === 'function') || (acc.saveData && typeof acc.saveData === 'function')) {
                acc.disable();
            }
        });
    }
});

Ext.reg('elements-elementaccordion', Phlexible.elements.ElementAccordion);