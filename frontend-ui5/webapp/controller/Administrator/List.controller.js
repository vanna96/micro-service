sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/AdministratorRepository",
    "my/app/util/Pagination"
], function (
    BaseController,
    AdministratorRepository,
    Pagination
) {
    "use strict";

    return BaseController.extend("my.app.controller.Administrator.List", {

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
                const paginationInfo = Pagination.getPaginationInfo(totalItems, pageSize, pageNumber);
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

        onNextPage: function() { 
            var currentPage = this.oModel.getProperty("/pagination/currentPage");
            var totalPages = this.oModel.getProperty("/pagination/totalPages");
            
            if (currentPage < totalPages) {
                currentPage++;
                this.loadData(currentPage);
            }
        },
        
        onPreviousPage: function() {
            var currentPage = this.oModel.getProperty("/pagination/currentPage");
            
            if (currentPage > 1) {
                currentPage--;
                this.loadData(currentPage);
            }
        },

        onFirstPage: function () {
            this.loadData(1);
        },

        onLastPage: function () {
            var totalPages = this.oModel.getProperty("/pagination/totalPages");
            this.loadData(totalPages);
        },

        handlerCreate: function(){
            this.oRouter.navTo("administrator_create");
        },

        handlerSearch: function(oEvent){
            this.oModel.setProperty('/search', oEvent.getParameter("query"))
            this.loadData(1);
        }
    });
});
