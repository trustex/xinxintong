xxtApp.factory('Mp', function($q, http2) {
    var Mp = function() {};
    Mp.prototype.getAuthapis = function(id) {
        var _this = this,
            deferred = $q.defer(),
            promise = deferred.promise;
        if (_this.authapis !== undefined) {
            deferred.resolve(_this.authapis);
        } else {
            http2.get('/rest/mp/authapi/get?valid=Y', function(rsp) {
                _this.authapis = rsp.data;
                deferred.resolve(rsp.data);
            });
        }
        return promise;
    };
    return Mp;
});
xxtApp.config(['$routeProvider', function($routeProvider) {
    $routeProvider.when('/rest/mp/app/enroll/detail', {
        templateUrl: '/views/default/mp/app/enroll/setting.html?_=1',
        controller: 'settingCtrl',
        resolve: {
            load: function($q) {
                var defer = $q.defer();
                (function() {
                    $.getScript('/views/default/mp/app/enroll/setting.js', function() {
                        defer.resolve();
                    });
                })();
                return defer.promise;
            }
        }
    }).when('/rest/mp/app/enroll/round', {
        templateUrl: '/views/default/mp/app/enroll/round.html?_=1',
        controller: 'roundCtrl',
        resolve: {
            load: function($q) {
                var defer = $q.defer();
                (function() {
                    $.getScript('/views/default/mp/app/enroll/round.js', function() {
                        defer.resolve();
                    });
                })();
                return defer.promise;
            }
        }
    }).when('/rest/mp/app/enroll/page', {
        templateUrl: '/views/default/mp/app/enroll/page.html?_=1',
        controller: 'pageCtrl',
        resolve: {
            load: function($q) {
                var defer = $q.defer();
                (function() {
                    $.getScript('/views/default/mp/app/enroll/page.js', function() {
                        defer.resolve();
                    });
                })();
                return defer.promise;
            }
        }
    }).when('/rest/mp/app/enroll/record', {
        templateUrl: '/views/default/mp/app/enroll/record.html?_=1',
        controller: 'recordCtrl',
        resolve: {
            load: function($q) {
                var defer = $q.defer();
                (function() {
                    $.getScript('/views/default/mp/app/enroll/record.js', function() {
                        defer.resolve();
                    });
                })();
                return defer.promise;
            }
        }
    }).when('/rest/mp/app/enroll/stat', {
        templateUrl: '/views/default/mp/app/enroll/stat.html?_=1',
        controller: 'statCtrl'
    }).when('/rest/mp/app/enroll/lottery', {
        templateUrl: '/views/default/mp/app/enroll/lottery.html?_=' + (new Date()).getTime(),
        controller: 'lotteryCtrl',
        resolve: {
            load: function($q) {
                var defer = $q.defer();
                (function() {
                    $.getScript('/views/default/mp/app/enroll/lottery.js', function() {
                        defer.resolve();
                    });
                })();
                return defer.promise;
            }
        }
    }).when('/rest/mp/app/enroll/accesslog', {
        templateUrl: '/views/default/mp/app/enroll/accesslog.html?_=1',
        controller: 'accesslogCtrl'
    }).otherwise({
        templateUrl: '/views/default/mp/app/enroll/setting.html?_=2',
        controller: 'settingCtrl'
    });
}]);
xxtApp.controller('enrollCtrl', ['$scope', '$location', 'http2', function($scope, $location, http2) {
    $scope.aid = $location.search().aid;
    $scope.subView = '';
    $scope.taskCodeEntryUrl = 'http://' + location.host + '/rest/q';
    $scope.back = function() {
        history.back();
    };
    $scope.canRounds = function() {
        $scope.editing.multi_rounds = 'Y';
        $scope.editing.rounds = [];
        $location.path('/rest/mp/app/enroll/round');
    };
    $scope.isInputPage = function(pageName) {
        if (!$scope.editing) {
            return false;
        }
        for (var i in $scope.editing.pages) {
            if ($scope.editing.pages[i].name === pageName && $scope.editing.pages[i].type === 'I') {
                return true;
            }
        }
        return false;
    };
    http2.get('/rest/mp/app/enroll/get?aid=' + $scope.aid, function(rsp) {
        $scope.editing = rsp.data;
        $scope.editing.tags = (!$scope.editing.tags || $scope.editing.tags.length === 0) ? [] : $scope.editing.tags.split(',');
        $scope.editing.type = 'enroll';
        $scope.persisted = angular.copy($scope.editing);
    });
    http2.get('/rest/mp/mpaccount/get', function(rsp) {
        $scope.mpaccount = rsp.data;
        $scope.hasParent = $scope.mpaccount.parent_mpid && $scope.mpaccount.parent_mpid.length;
    });
    http2.get('/rest/mp/feature/get?fields=matter_visible_to_creater', function(rsp) {
        $scope.features = rsp.data;
    });
}]);
xxtApp.controller('statCtrl', ['$scope', 'http2', function($scope, http2) {
    $scope.$parent.subView = 'stat';
    http2.get('/rest/mp/app/enroll/statGet?aid=' + $scope.aid, function(rsp) {
        $scope.stat = rsp.data;
    });
}]);
xxtApp.controller('accesslogCtrl', ['$scope', 'http2', function($scope, http2) {
    $scope.$parent.subView = 'accesslog';
}]);