sap.ui.define([ ], () => {
"use strict";
	return {
		handlerInputChange: function (oEvent) {
            var oSource = oEvent.getSource();
            var pKey = oSource?.getProperty('name') ?? '';
            if (!pKey) return;

            var pValue; 

            switch (pKey) {
                case "status": 
                    var iIndex = oSource.getSelectedIndex();
                    if (iIndex !== -1) {
                        var oSelectedButton = oSource.getButtons()[iIndex];
                        pValue = oSelectedButton.getText();
                    }
                    break;

                default:
                    if (
                        pKey === 'cambobox' || 
                        pKey === 'select'
                    ) pValue = oSource.getSelectedKey();
                    else pValue = oSource.getValue();
                    break;
            }
            console.log([pValue, pKey])
            if (pValue !== undefined) this.oModel.setProperty(`/${pKey}`, pValue);
        },

        handlerRadioChange: function(oEvent) {
            const oRadioGroup = oEvent.getSource(); 
            const localId = oRadioGroup.getId().split("--").pop(); 
            const selectedIndex = oRadioGroup.getSelectedIndex();

            if (selectedIndex !== -1) {
                const selectedButton = oRadioGroup.getButtons()[selectedIndex];
                const selectedText = selectedButton.getText();
                this.oModel.setProperty(`/${localId}`, selectedText);
            } 
        } 
	};
});
