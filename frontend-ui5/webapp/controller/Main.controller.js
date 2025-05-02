sap.ui.define([
    "sap/ui/Device",
    "sap/ui/core/mvc/Controller",
    "sap/ui/core/Fragment",
    "my/app/util/Cookie",
    'sap/m/Popover',
    'sap/m/Bar',
	'sap/m/Title',
    'sap/ui/core/IconPool',
    'sap/m/Button',
    'sap/m/MessageView',
    'sap/m/MessageItem',
    'sap/ui/core/Icon',
	'sap/m/Link',
    'sap/ui/model/json/JSONModel',
], (
    Device,
    Controller,
    Fragment,
    Cookie,
    Popover,
    Bar,
    Title,
    IconPool,
    Button,
    MessageView,
    MessageItem,
    Icon,
    Link,
    JSONModel
) => {
    "use strict";

    return Controller.extend("my.app.controller.Main", {
        onInit: function () {
            this.oModel = new sap.ui.model.json.JSONModel();
            this.oModel.loadData(sap.ui.require.toUrl("my/app/model/menu.json"));
            this.getView().setModel(this.oModel, "menu"); 
            const oRouter = this.getOwnerComponent().getRouter();  
            oRouter.initialize();
 
            oRouter.attachRouteMatched(this._onRouteMatched, this);
        },

        _onRouteMatched: function () {
            this._handleResize();
            this.hadleImplementNotification();
        },

        hadleImplementNotification(){
            const that  = this;
            
            const oBackButton = new Button({
                icon: IconPool.getIconURI("nav-back"),
                visible: false,
                press: function() {
                    that.oMessageView.navigateBack();
                    that._oPopover.focus();
                    this.setVisible(false);
                }
            });

            const oLink = new Link({
                text: "Show more information",
                href: "http://sap.com",
                target: "_blank"
            });
        
            const oMessageTemplate = new MessageItem({
                type: '{type}',
                title: '{title}',
                description: '{description}',
                subtitle: '{subtitle}',
                counter: '{counter}',
                markupDescription: "{markupDescription}",
                link: oLink
            });

            this.oMessageView = new MessageView({
                showDetailsPageHeader: false,
                itemSelect: function() {
                    oBackButton.setVisible(true);
                },
                items: {
                    path: "/",
                    template: oMessageTemplate
                }
            });

        
            const oCloseButton = new Button({
                text: "Close",
                press: function() {
                    that._oPopover.close();
                }
            }).addStyleClass("sapUiTinyMarginEnd") 
        
            this._oPopover = new Popover({
                customHeader: new Bar({
                    contentLeft: [oBackButton],
                    contentMiddle: [
                        new Title({ text: "Messages" })
                    ]
                }),
                contentWidth: "440px",
                contentHeight: "440px",
                verticalScrolling: false,
                modal: true,
                content: [this.oMessageView],
                footer: new Bar({
                    contentRight: oCloseButton
                }),
                placement: "Bottom"
            });
        },

        _handleResize: function () {
            const oSplitApp = this.byId("splitAppControl");
            const phone = Device.system.phone;
            const desktop = Device.system.desktop;

            if (desktop) oSplitApp.showMaster();
            if (phone) {
                oSplitApp.hideMaster();
                oSplitApp.to(oSplitApp.getDetailPages()[0].getId());
            }
        },

        onSideNavButtonPress: function () {
            const oSplitApp = this.byId("splitAppControl");
            const phone = Device.system.phone;
            const desktop = Device.system.desktop;
            const isShowMode = oSplitApp.getMode() === "ShowHideMode";

            if (desktop) {
                if (
                    oSplitApp.isMasterShown() || isShowMode
                ) {
                    oSplitApp.hideMaster();
                    oSplitApp.setMode("HideMode");
                } else {
                    oSplitApp.showMaster();
                    oSplitApp.setMode("ShowHideMode");
                }
            }

            if (phone) {
                if (
                    oSplitApp.isMasterShown() || isShowMode
                ) {
                    oSplitApp.setMode("HideMode");
                    oSplitApp.hideMaster();
                    oSplitApp.to(oSplitApp.getDetailPages()[0].getId());
                } else {
                    oSplitApp.setMode("ShowHideMode");
                    oSplitApp.showMaster();
                    oSplitApp.to(oSplitApp.getMasterPages()[0].getId());
                }
            }
        },

        onItemSelect: function (oEvent) {
            const sKey = oEvent.getParameter("item").getKey();
            const oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            oRouter.navTo(sKey);
        },

        onMyAccount: function () {
            const oView = this.getView(),
                oButton = oView.byId("button");

            if (!this._oMenuFragment) {
                this._oMenuFragment = Fragment.load({
                    id: oView.getId(),
                    name: "my.app.view.layouts.Menu",
                    controller: this
                }).then(function (oMenu) {
                    oMenu.openBy(oButton);
                    this._oMenuFragment = oMenu;
                    return this._oMenuFragment;
                }.bind(this));
            } else {
                this._oMenuFragment.openBy(oButton);
            }
        },

        onLogoutPress: function () {
            Cookie.deleteCookie("B1SESSION");
            const oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            oRouter.navTo("login");
        },

        handleNotification: function(oEvent) {
            
            const aMockMessages = [{
                type: 'Error',
                title: 'Error message',
                description: 'First Error message description. \n' +
                    'Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod',
                subtitle: 'Example of subtitle',
                counter: 1
            }, {
                type: 'Warning',
                title: 'Warning without description',
                description: ''
            }, {
                type: 'Success',
                title: 'Success message',
                description: 'First Success message description',
                subtitle: 'Example of subtitle',
                counter: 1
            }, {
                type: 'Error',
                title: 'Error message',
                description: 'Second Error message description',
                subtitle: 'Example of subtitle',
                counter: 2
            }, {
                type: 'Information',
                title: 'Information message',
                description: 'First Information message description',
                subtitle: 'Example of subtitle',
                counter: 1
            }];

            const oModel = new JSONModel(aMockMessages);
            this.getView().setModel(oModel);  
        
            this.oMessageView.setModel(oModel).navigateBack();
            this._oPopover.openBy(oEvent.getSource());
        }        
        
    });

});
