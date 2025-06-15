sap.ui.define([
    "sap/ui/core/mvc/Controller", 
    "sap/ui/core/BusyIndicator",
    "my/app/repository/PermissionRepository",
    "my/app/repository/CategoryRepository",
], function (
    Controller,
    BusyIndicator,
    PermissionRepository,
    CategoryRepository
) {
    "use strict";

    return Controller.extend("my.app.controller.Dashboard", {

        onInit: async function () { 
            BusyIndicator.show();

            document.title = "Dashboard";
            this.oModel = new sap.ui.model.json.JSONModel({});
            this.getView().setModel(this.oModel, "dashboard");

            // this._oRouter = this.getOwnerComponent().getRouter();
            // this._oRouter.attachRouteMatched(this.onRouteMatched, this); 

            const [aValidMenuItems, count] = await this._loadMenu(this.oModel);   
            
            const aSections = this._createObjectPageSections(aValidMenuItems, count);

            const oPage = this.byId("dashboardPageLayout");
            aSections.forEach(section => oPage.addSection(section));

            BusyIndicator.hide();
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
            let menuItems = data?.menuItems || []; 
            const allowedTitles = permissions.map(p => p.key); 
            let menus = []; 
            const repositories = {
                CategoryRepository 
            };
            
            function filterMenuItems(items) {
                return items
                    .map(item => {
                        if (item.subItems) {
                            item.subItems = filterMenuItems(item.subItems);
                        }
        
                        const isAllowed = allowedTitles.includes(item.title);
                        const hasAllowedSubItems = item.subItems && item.subItems.length > 0;

                        if(isAllowed || hasAllowedSubItems){ 
                            if(hasAllowedSubItems) menus.push(...item.subItems)
                            return item
                        }
                        return null; 
                    })
                    .filter(item => item !== null);
            }  

            const aValidMenuItems = filterMenuItems(menuItems).filter(item => item.subItems && item.subItems.length > 0);
            const aMenuWithCount = await Promise.all(menus.map(async (menu) => {
                if (menu.repo) {
                    const repoName = `${menu.repo}Repository`;
                    const repo = repositories[repoName];
                    const count = await repo.count();
                    return {
                        ...menu,
                        count
                    };
                } else {
                    return {
                        ...menu,
                        count: " "
                    };
                }
            }));            
            
            return [aValidMenuItems, aMenuWithCount]; 
        },

        _createObjectPageSections: function (aMenuItems, count) { 
            var that = this;
            const aSections = []; 
            aMenuItems.forEach(oSection => {  
                const oPageSection = new sap.uxap.ObjectPageSection({
                    title: oSection.title,
                    titleUppercase: false,
                    subSections: [
                        new sap.uxap.ObjectPageSubSection({
                            title: oSection.title,
                            mode: "Expanded",
                            blocks: [
                                new sap.f.GridContainer({ 
                                    snapToRow: true,
                                    items: oSection?.subItems?.map(oItem => {
                                        return new sap.m.GenericTile({
                                            header: oItem.title,
                                            press: function (oEvent) {
                                                var sTileKey = oEvent.getSource().getCustomData()[0].getValue();
                                                that.getOwnerComponent().getRouter().navTo(sTileKey);
                                            },
                                            layoutData: new sap.f.GridContainerItemLayoutData({
                                                minRows: 2,
                                                columns: 2
                                            }),
                                            tileContent: [
                                                new sap.m.TileContent({
                                                    content: new sap.m.NumericContent({
                                                        value: count?.find(({key}) => key == oItem.key)?.count ?? ' ',
                                                        withMargin: false
                                                    })
                                                })
                                            ],
                                            customData: [
                                                new sap.ui.core.CustomData({
                                                    key: "navKey",
                                                    value: oItem.key
                                                })
                                            ]
                                        });
                                    })
                                })
                            ]
                        })
                    ]
                });
        
                aSections.push(oPageSection);
            });
            return aSections;
        }
    });
});
