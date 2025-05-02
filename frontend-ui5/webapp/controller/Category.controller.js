sap.ui.define([ 
    "my/app/controller/Base.controller",
    "my/app/repository/CategoryRepository",
    "my/app/util/Pagination"
], function (
    BaseController,
    CategoryRepository,
    Pagination
) {
    "use strict";

    return BaseController.extend("my.app.controller.Category", {

        onInit: function () {
            BaseController.prototype.onInit.call(this);
            document.title = "Category"; 

            this.oRouter.getRoute("category").attachPatternMatched(this.onListing, this);
            // this.oRouter.getRoute("category_create").attachPatternMatched(this.onCreate, this);
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
                isLoading: true,
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
                const data = await CategoryRepository.get(oParams);
                const totalItems = data["odata.count"];
                const totalPages = Pagination.getTotalPages(totalItems, pageSize);
                this.oModel.setProperty("/data", data.value);
                this.oModel.setProperty("/pagination/totalItems", totalItems);
                this.oModel.setProperty("/pagination/totalPages", totalPages);
                const paginationInfo = Pagination.getPaginationInfo(totalItems, pageSize, pageNumber);
                this.oModel.setProperty("/pagination", paginationInfo);

            } catch (error) {
                console.error("Error loading data:", error);
            }
            this.oModel.setProperty("/isLoading", false);
        },
    });
});
