sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/UnitofMeasureRepository",
    "my/app/util/Pagination"
], function (
    BaseController,
    UnitofMeasureRepository,
    Pagination
) {
    "use strict";

    return BaseController.extend("my.app.controller.UoM.List", {

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            document.title = "UoM";  
            this.oRouter.getRoute("uom").attachPatternMatched(this.onListing, this); 
        },

        onListing: async function () { 
            this.oModel.setData({
                pagination: {
                    currentPage: 1,
                    pageSize: 20,
                    totalItems: 0,
                    totalPages: 0,
                    hasPrevious: false,
                    hasNext: false
                },
                isLoading: false,
                search: {}
            });

            await this.loadData(1);
        },

        loadData: async function (pageNumber) {
            this.oModel.setProperty("/isLoading", true);
            const pageSize = this.oModel.getProperty("/pagination/pageSize");
            const search = this.oModel.getProperty("/search");
            const skip = (pageNumber - 1) * pageSize;
            const filters = [];

            const oParams = {
                $top: pageSize,
                $skip: skip,
                $count: true
            };

            if (search) {
                // if (search.Search) filters.push(search.Search);
                // if (search.CustomerName) filters.push(search.CustomerName);
                // if (search.DocumentNo) filters.push(search.DocumentNo);
                // if (search.SaleEmployee) filters.push(search.SaleEmployee);
                // if (search.CustomerCode) filters.push(search.CustomerCode);
                // if (search.Status) filters.push(search.Status);
                // if (search.DocDate) filters.push(search.DocDate);
            }

            if (filters.length > 0) oParams.$filter = filters.join(' and ');

            try {
                const data = await UnitofMeasureRepository.get(oParams);
                const totalItems = data["odata.count"]; 
                const paginationInfo = Pagination.getPaginationInfo(totalItems, pageSize, pageNumber);
                this.oModel.setProperty("/data", data.value); 
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
            this.oRouter.navTo("uom_edit", { id: sId });
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
            this.oRouter.navTo("uom_create");
        }
    });
});
