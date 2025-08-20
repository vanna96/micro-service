sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/AdministratorRepository",
    "my/app/util/Pagination",
    "my/app/util/ToolbarList",
], function (
    BaseController,
    AdministratorRepository,
    Pagination,
    ToolbarList
) {
    "use strict";

    return BaseController.extend("my.app.controller.Administrator.List", Object.assign(Pagination, ToolbarList, {

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            document.title = "Administrator";  
            this.oRouter.getRoute("administrator").attachPatternMatched(this.onListing, this); 
        },

        onListing: async function () { 
            this.oModel.setData({
                pageSize: 20,
                isLoading: false
            });

            await this.loadData(1);
        },

        loadData: async function (pageNumber) {
            this.oModel.setProperty("/isLoading", true);
            const pageSize = this.oModel.getProperty("/pageSize");
            const search = this.oModel.getProperty("/search")

            const oParams = {
                per_page: pageSize,
                page: pageNumber,
                search: search
            };

            try {
                const res = await AdministratorRepository.get(oParams); 
                const totalItems = res["total"] ?? 0; 
                const paginationInfo = this.getPaginationInfo(totalItems, pageSize, pageNumber);
                this.oModel.setProperty("/data", res['data'] ?? []); 
                this.oModel.setProperty("/pagination", paginationInfo);

            } catch (error) {
                console.error("Error loading data:", error);
            } finally{
                this.oModel.setProperty("/isLoading", false);
            }
        },

        handlerEdit: function (oEvent) {
            var oItem = oEvent.getSource();
            var sId = oItem.getBindingContext("model").getProperty("id");
            this.oRouter.navTo("administrator_edit", { id: sId });
        },

        handlerCreate: function(){
            this.oRouter.navTo("administrator_create");
        },
    }));
});
