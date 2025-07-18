// var head = document.getElementsByTagName('head')[0];
// var link = document.createElement('link');
// link.rel = 'stylesheet';
// link.type = 'text/css';
// link.href = '/dist/css/other/scrollbar.css';
// head.appendChild(link);
var div = document.createElement("DIV");
div.className = "progress-container fixed-top";
div.innerHTML = '<span class="progress-bar"></span>';
document.body.insertBefore(div, document.body.firstChild);
$(function () {
    scrollProgressBar();
});

function scrollProgressBar() {
    $(document).on("scroll", setProgressWidth);
    $(window).on("resize", setProgressWidth);
}

function setProgressWidth() {
    $(".progress-bar").css({width: getPageWidth()});
}

function getPageWidth() {
    // Calculate width in percentage
    let current = $(window).scrollTop();
    let max = $(document).height() - $(window).height();
    let width = (current / max) * 100;
    return width + "%";
}