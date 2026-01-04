/**
 * Homepage JavaScript functions
 * CSP-compliant external JavaScript file
 */

// Get base URL from global variable or construct it
var homeUrl = window.location.origin + '/';

function page_refresh() {
    pool_current_refresh();
    pool_history_refresh();
    pool_coins_info_refresh();
}

function select_algo(algo) {
    window.location.href = homeUrl + 'site/algo?algo=' + algo + '&r=/';
}

function pool_current_ready(data) {
    $('#pool_current_results').html(data);
}

function pool_current_refresh() {
    var url = homeUrl + "site/current_results";
    $.get(url, '', pool_current_ready);
}

function pool_history_ready(data) {
    $('#pool_history_results').html(data);
}

function pool_history_refresh() {
    var url = homeUrl + "site/history_results";
    $.get(url, '', pool_history_ready);
}

function pool_coins_info_ready(data) {
    $('#pool_coins_info').html(data);
}

function pool_coins_info_refresh() {
    var url = homeUrl + "site/coins_info";
    $.get(url, '', pool_coins_info_ready);
}