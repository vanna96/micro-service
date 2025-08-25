sap.ui.define(["require"], (require) => {
    "use strict";
    return {
        resolvePath(sPath) {
            return require.toUrl("../") + sPath;
        },

        dateFormat: function ({ format = 'MM/dd/yyyy', value }) {
            if (!value) return ``;

            const date = new Date(value);
            const pad = (number) => number.toString().padStart(2, '0');

            const getWeekOfYear = (date) => {
                const start = new Date(date.getFullYear(), 0, 1);
                const diff = date - start + ((start.getDay() + 1) * 86400000);
                return Math.ceil(diff / 604800000);
            };

            const hour12 = date.getHours() % 12 || 12;

            const formatMap = {
                'yyyy': date.getFullYear(),  // 2024
                'yy': date.getFullYear().toString().slice(-2),  // 24
                'MMMM': new Intl.DateTimeFormat('en-US', { month: 'long' }).format(date),  // September
                'MMM': new Intl.DateTimeFormat('en-US', { month: 'short' }).format(date),  // Sep
                'MM': pad(date.getMonth() + 1),  // 09
                'M': date.getMonth() + 1,  // 9
                'dd': pad(date.getDate()),  // 11
                'd': date.getDate(),  // 11
                'HH': pad(date.getHours()),  // 14
                'H': date.getHours(),  // 14
                'hh': pad(hour12), // 02
                'h': hour12,// 02
                'mm': pad(date.getMinutes()),  // 35
                'm': date.getMinutes(),  // 35
                'ss': pad(date.getSeconds()),  // 15
                's': date.getSeconds(),  // 15
                'a': date.getHours() >= 12 ? 'PM' : 'AM',  // PM
                'A': date.getHours() >= 12 ? 'PM' : 'AM',  // PM
                'ww': getWeekOfYear(date),  // 37 (Week of the year)
                'W': getWeekOfYear(date),  // 37 (Week of the year)
                'Q': Math.ceil((date.getMonth() + 1) / 3),  // 3 (3rd quarter)
                'Z': date.toISOString().slice(19, 25)  // .000Z (ISO time zone info)
            };

            // Handle the format tokens, ensuring that any non-format character (like space, hyphen) is preserved
            return format.split(/(yyyy|yy|MMMM|MMM|MM|M|dd|d|HH|H|hh|h|mm|m|ss|s|a|A|ww|W|Q|Z)/g)
                .map(part => formatMap[part] !== undefined ? formatMap[part] : part) // Replace format tokens with values
                .join(''); // Join without additional spaces to preserve the original formatting
        },

        trans: function (sKey) {
            try {

                const oI18nModel = sap.ui.getCore().getModel("i18n");
                if (!oI18nModel) return sKey;

                const oBundle = oI18nModel.getResourceBundle();
                if (!oBundle) return sKey;

                return oBundle.getText(sKey) || sKey;
            } catch (e) {
                return sKey;
            }
        }
    };
});
