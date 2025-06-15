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
    'my/app/repository/PermissionRepository',
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
    JSONModel,
    PermissionRepository
) => {
    "use strict";

    return Controller.extend("my.app.controller.Main", {
        onInit: function () {
            this._oRouter = sap.ui.core.UIComponent.getRouterFor(this);
            // this._oRouter.attachRouteMatched(this._onRouteMatched, this);
            this._oRouter.attachRoutePatternMatched(this._onRouteMatched, this);

            this.oMenuModel = new sap.ui.model.json.JSONModel({
                selectedKey: ""
            });
        
            this.getView().setModel(this.oMenuModel, "menu");
            this._loadMenu(this.oMenuModel);
        
            // Initialize router after rendering
            this.getView()
            .byId("appContainer")
            .addDelegate({
                onAfterRendering: () => this._oRouter.initialize()
            });  
        },

        _onRouteMatched: function (oEvent) {
            const sRouteName = oEvent.getParameter("name");
            this.sRouteName = sRouteName || "dashboard";
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
             
            if (desktop) {
                oSplitApp.showMaster();
                oSplitApp.setMode("ShowHideMode");
                oSplitApp.to(oSplitApp.getMasterPages()[0].getId());
            }
            if (phone) {
                oSplitApp.hideMaster();
                oSplitApp.setMode("HideMode");
                oSplitApp.to(oSplitApp.getDetailPages()[0].getId());
            }
        },

        onSideNavButtonPress: function () {
            const oSplitApp = this.byId("splitAppControl");
            const phone = Device.system.phone;
            const desktop = Device.system.desktop;
            const isShowMode = oSplitApp.getMode() === "ShowHideMode"; 
            
            if (
                // oSplitApp.isMasterShown() || 
                isShowMode
            ) {
                oSplitApp.hideMaster();
                oSplitApp.setMode("HideMode");
                oSplitApp.to(oSplitApp.getDetailPages()[0].getId());
            } else {
                oSplitApp.showMaster();
                oSplitApp.setMode("ShowHideMode");
                oSplitApp.to(oSplitApp.getMasterPages()[0].getId());
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

            const oModel = new sap.ui.model.json.JSONModel(aMockMessages);

            // Safety check
            const oView = this.getView?.();
            if (!oView) {
                console.error("View is undefined in handleNotification");
                return;
            }

            oView.setModel(oModel);

            if (this.oMessageView && this._oPopover) {
                this.oMessageView.setModel(oModel);
                this.oMessageView.navigateBack();
                this._oPopover.openBy(oEvent.getSource());
            } else {
                console.error("MessageView or Popover not initialized");
            }
        },

        _loadMenu: async function(oMenuModel) {
            const url = sap.ui.require.toUrl("my/app/assets/static/menu.json");
            const permissions = (await PermissionRepository.get())?.value || [];  
            
            const loadDataAsync = (model, url) => {
                return new Promise((resolve, reject) => {
                    model.attachRequestCompleted(resolve);
                    model.attachRequestFailed(reject);
                    model.loadData(url);
                });
            };
        
            await loadDataAsync(oMenuModel, url);
        
            const data = oMenuModel.getData();
            const menuItems = data?.menuItems || []; 
            const allowedTitles = permissions.map(p => p.key); 
            const sRouteName = this.sRouteName;
            console.log(sRouteName);
            
            function filterMenuItems(items) {
                return items
                    .map(item => { 
                        if (item.subItems) {
                            item.subItems = filterMenuItems(item.subItems);
                        }
        
                        const isAllowed = allowedTitles.includes(item.title);
                        const hasAllowedSubItems = item.subItems && item.subItems.length > 0;
                        const shouldExpand = item.subItems?.some(sub => sub.key === sRouteName);

                        return (isAllowed || hasAllowedSubItems) ? {
                            ...item,
                            expanded: shouldExpand
                        } : null;
                    })
                    .filter(item => item !== null);
            } 
             
            oMenuModel.setProperty("/menuItems", filterMenuItems(menuItems));
            oMenuModel.setProperty("/selectedKey", this.sRouteName ?? 'dasboard');  
        }               
        
    });

});
