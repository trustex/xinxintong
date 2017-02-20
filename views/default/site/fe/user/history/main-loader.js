window.loading = {
    finish: function() {
        var eleLoading, eleStyle;
        eleLoading = document.querySelector('.loading');
        eleLoading.parentNode.removeChild(eleLoading);
        eleStyle = document.querySelector('#loadingStyle');
        eleStyle.parentNode.removeChild(eleStyle);
    },
    load: function() {
        require.config({
            paths: {
                "angular": "/static/js/angular.min",
            },
            shim: {
                "angular": {
                    exports: "angular"
                },
            },
            deps: ['/views/default/site/fe/user/history/main.js'],
            urlArgs: "bust=" + (new Date() * 1)
        });
    }
};
window.loading.load();
