var D_COL_WID = 200;
var hasChanged = false;

function setChanged(flag = true) {
    hasChanged = flag;
}
var md_key_press_event = {
    success: (layero, index) => {
        this.keydownConfirm = (event) => {
            let codeVal = event.which;
            if (codeVal === 27) {
                layer.close(index);
                return false;
            }
            if (codeVal === 13) {
                $('#searchBtn').trigger('click');
            }
        };
        $('body').on('keydown', this.keydownConfirm);
    },
    end: () => {
        $(document).off('keydown', this.keydownConfirm);
    }
};
var loadTableCols = function (tableFlag, type = 'hide') {
    let cacheKey = 'table_hidden';
    let field = 'hide';
    if (type == 'width') {
        cacheKey = 'table_width';
        field = 'width';
    }
    let colsDict = new Map();
    let cacheData = localStorage.getItem(cacheKey + tableFlag);
    let arr = JSON.parse(cacheData);
    if (!arr) {
        return colsDict;
    }
    $.each(arr, function (index, ele) {
        colsDict.set(ele.field, ele[field]);
    });
    return colsDict;
};
var saveTableColWidth = function (obj, tableFlag) {
    let col = "";
    $.each(obj.config.cols[0], function (index, ele) {
        let field = ele.field;
        let width = ele.width;
        if (typeof field === 'undefined') {
            return;
        }
        if (width) {
            col += '{"field":"' + field + '","width":"' + width + '"},';
        }
    })
    localStorage.setItem("table_width" + tableFlag + "", "[" + col.slice(0, -1) + "]");
}
var saveTableCols = function (obj, tableFlag) {
    let tableHidden = "";
    $.each(obj.config.cols[0], function (index, ele) {
        let field = ele.field;
        let hide = Boolean(ele.hide);
        if (field) {
            tableHidden += '{"field":"' + field + '","hide":' + hide + '},';
        }
    })
    localStorage.setItem("table_hidden" + tableFlag + "", "[" + tableHidden.slice(0, -1) + "]");
}
function objectifyForm(formArray) {
    let returnArray = {};
    for (let i = 0; i < formArray.length; i++) {
        returnArray[formArray[i]['name']] = formArray[i]['value'];
    }
    return returnArray;
}

function array2UrlParams(arr) {
    var out = [];
    for (var key in arr) {
        if (arr.hasOwnProperty(key)) {
            out.push(key + '=' + encodeURIComponent(arr[key]));
        }
    }
    return out.join('&');
}

/**
 * 复制到剪切板
 * @param text
 * @returns {string|boolean|void}
 */
function copyToClipboard(text) {
    if (window.clipboardData && window.clipboardData.setData) {
        return window.clipboardData.setData("Text", text);
    } else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
        let textarea = document.createElement("textarea");
        textarea.textContent = text;
        textarea.style.position = "fixed";
        document.body.appendChild(textarea);
        textarea.select();
        try {
            return document.execCommand("copy");
        } catch (ex) {
            console.warn("Copy to clipboard failed.", ex);
            return prompt("复制请按键: Ctrl+C, Enter", text);
        } finally {
            document.body.removeChild(textarea);
        }
    }
}

var Toast = Swal.mixin({
    toast: true,
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    iconColor: 'white',
    customClass: {
        popup: 'colored-toast',
    },
    didOpen: (toast) => {
        toast.onmousedown = Swal.close;
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});
function tipInfo(msg, opt = {}) {
    let tOpt = {
      icon: "info",
      title: msg,
      showConfirmButton: false,
      timer: 1500
    };
    if (opt) {
        for (let key in opt) {
            tOpt[key] = opt[key];
        }
    }
    return Swal.fire(tOpt);
}
function tipSuccess(msg, opt = {}) {
    if (!opt.timer) {
        opt.timer = 1500;
    }
    return tipMsg(msg, 'success', opt);
}
function tipError(msg, opt = {}) {
    return tipMsg(msg, 'error', opt);
}
function tipMsg(msg, type = 'success', opt = {}) {
    let tOpt = {
        icon: type,
        titleText: msg
    };
    if (opt) {
        for (let key in opt) {
            tOpt[key] = opt[key];
        }
    }
    return Toast.fire(tOpt);
}
function delConfirm(text, title = '') {
    return swalFire({
        titleText: '确认删除'+title+'？',
        text: text,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "删除",
        confirmButtonColor: '#d33',
        cancelButtonText: "取消"
    });
}
function opConfirm(title, text = '') {
    return swalFire({
        titleText: title,
        text: text,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "确认",
        cancelButtonText: "取消"
    });
}
function swalFire(opt) {
    return Swal.fire(opt);
}
var BTN_HIDE = 'layui-hide';
var tableParseData = {
    error: (res) => {
        if (typeof res.code === 'undefined') {
            layer.msg('请求出错，请刷新页面', function () {
            });
        }
    }
};

function getThemeColor() {
    var c = localStorage.getItem("theme-color-color");
    if (c == null) {
        return '#16baaa';
    }
    return c;
}

function renderXmSelect(el, data, name, radio = false, opts = {}) {
    let option = {
        el: el,
        radio: radio,
        theme: {
            color: getThemeColor(),
        },
        filterable: true,
        direction: 'down',
        autoRow: true,
        data: data,
        name: name,
        on: function (data) {
            if (data.change.length) {
                setChanged();
            }
        }
    };
    if (radio) {
        option.radio = true;
        option.clickClose = true;
    }
    if (opts) {
        for (let key in opts) {
            option[key] = opts[key];
        }
    }
    return xmSelect.render(option);
}

// 查看表格中多个图片
function openTableImages(data) {
    let _list = [];
    let startPos = 0;
    let pos = 0;
    layui.table.getData(data.config.id).forEach(function (item) {
        if (item.src == data.data.src) {
            startPos = pos;
        }
        if (item.icon.indexOf('fa-image') < 0) {
            return false;
        }
        pos++;
        _list.push({
            "alt": item.base,
            "src": item.src,
        });
    });
    layer.photos({
        photos: {
            "start": startPos,
            "data": _list,
        }
    });
}