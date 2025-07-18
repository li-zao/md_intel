layui.define(['jquery', 'element'], function (exports) {
    "use strict";

    var MOD_NAME = 'count',
        $ = layui.jquery,
        element = layui.element;

    var count = new function () {

        this.up = function (targetEle, options) {

            options = options || {};

            var $this = document.getElementById(targetEle),
                time = options.time,
                finalNum = parseFloat(options.num),
                regulator = options.regulator,
                unit = options.unit ? options.unit : '',
                step = finalNum / (time / regulator),
                count = 0.00,
                initial = 0;

            var timer = setInterval(function () {
                count = count + step;
                if (count >= finalNum) {
                    clearInterval(timer);
                    count = finalNum;
                }
                var t = count.toFixed(options.bit ? options.bit : 0);
                if (t == initial) {
                    $this.innerHTML = initial.toString() + ' ' + unit;
                    return;
                }
                initial = t;
                $this.innerHTML = initial.toString() + ' ' + unit;
            }, 30);
        }

    }
    exports(MOD_NAME, count);
});