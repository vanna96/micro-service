sap.ui.define([], () => {
    "use strict";
    return {
        handlerInputChange: function (oEvent) {
            var oSource = oEvent.getSource();
            var pKey = oSource?.getProperty('name') ?? '';
            if (!pKey) return;

            var pValue;
            console.log(`key: ${pKey}`)
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
                        pKey === 'gender' ||
                        pKey === 'parent'
                    ) pValue = oSource.getSelectedKey();
                    else pValue = oSource.getValue();
                    break;
            }
            console.log([pKey, pValue])
            if (pValue !== undefined) this.oModel.setProperty(`/${pKey}`, pValue);
        },

        handlerRadioChange: function (oEvent) {
            const oRadioGroup = oEvent.getSource();
            const localId = oRadioGroup.getId().split("--").pop();
            const selectedIndex = oRadioGroup.getSelectedIndex();

            if (selectedIndex !== -1) {
                const selectedButton = oRadioGroup.getButtons()[selectedIndex];
                const selectedText = selectedButton.getText();
                this.oModel.setProperty(`/${localId}`, selectedText);
            }
        },

        validateRequiredFields: function ({
            data,
            requiredFields
        }) {

            const isFieldMissing = (key, required_if) => {
                if (key.includes(".*.")) {
                    let [parent, child] = key.split(".*.");
                    if (!Array.isArray(data[parent]) || data[parent].some(item => {
                        if (required_if) {
                            return !item[child] && required_if(item);
                        }
                        return !item[child];
                    })) {
                        return true;
                    }
                    return false;
                }

                if (required_if) return !data[key] && required_if(data);
                else return !data[key];
            };

            let that = this;
            return requiredFields.some(field => {
                if (isFieldMissing(field.key, field.required_if)) {
                    let oErrorMessageDialog = new sap.m.Dialog({
                        type: sap.m.DialogType.Message,
                        title: "Error",
                        state: sap.ui.core.ValueState.Error,
                        content: new sap.m.Text({ text: field.msg }),
                        beginButton: new sap.m.Button({
                            type: sap.m.ButtonType.Emphasized,
                            text: "OK",
                            press: function () {
                                oErrorMessageDialog.close();
                                oErrorMessageDialog.destroy();
                                oErrorMessageDialog = null;
                            }
                        })
                    });
                    oErrorMessageDialog.open();
                    return true;
                }
                return false;
            });
        },

        errMessageDialog: function (error) {
            let errorMessage = "Unknown error occurred.";
            if (error && error.responseJSON) {
                if (error.responseJSON && error.responseJSON.message) {
                    errorMessage = error.responseJSON.message;
                }
            }

            this.oErrorMessageDialog = new sap.m.Dialog({
                type: sap.m.DialogType.Message,
                title: "Error",
                state: sap.ui.core.ValueState.Error,
                content: new sap.m.Text({ text: errorMessage }),
                beginButton: new sap.m.Button({
                    type: sap.m.ButtonType.Emphasized,
                    text: "OK",
                    press: function () {
                        this.oErrorMessageDialog.close();
                    }.bind(this)
                })
            });

            this.oErrorMessageDialog.open();
        },

        smgDialog: function ({
            title = '',
            type = 'Information',
            smg = '',
            txtButton = 'OK',
            txtCancel = 'Cancel',
            onOk = null,
            onCancel = null,
            content = null
        } = {}) {
            const valueStates = sap.ui.core.ValueState;
            const allowedStates = ['None', 'Error', 'Warning', 'Success', 'Information'];
            const dialogState = allowedStates.includes(type) ? valueStates[type] : valueStates.Error;
            if (!content) content = new sap.m.Text({ text: smg })

            this.smgDialog = new sap.m.Dialog({
                type: sap.m.DialogType.Message,
                title: title,
                state: dialogState,
                content: content,
                beginButton: new sap.m.Button({
                    type: sap.m.ButtonType.Emphasized,
                    text: txtButton,
                    press: function () {
                        if (typeof onOk === 'function') onOk();
                        this.smgDialog.close();
                        this.smgDialog.destroy();
                        delete this.smgDialog;
                        return true;
                    }.bind(this)
                }),

                endButton: new sap.m.Button({
                    text: txtCancel,
                    press: function () {
                        if (typeof onCancel === 'function') onCancel();
                        this.smgDialog.close();
                        this.smgDialog.destroy();
                        delete this.smgDialog;
                        return false;
                    }.bind(this)
                })
            });

            this.smgDialog.open();
        }

    };
});
