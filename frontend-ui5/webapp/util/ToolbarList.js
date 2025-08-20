sap.ui.define([], function() {
    "use strict";

    return { 

        handlerSearch: function(oEvent){
            this.loadData(1);
        },

        handlerRefreshList: function(){ 
            this.loadData(1);
        },

        handlerResetList: function(){
            this.oModel.setProperty('/search', null)
            this.loadData(1);
        },

        handlerSearchChange: function(oEvent){
            var sValue = oEvent.getSource().getValue(); 
            this.oModel.setProperty('/search', sValue)
        }
    };
});
