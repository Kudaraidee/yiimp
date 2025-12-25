
var auto_delay = 60000;
var auto_max_time = 3600000;
var auto_start_time = new Date().getTime();

// Wait for both jQuery and page_refresh function to be available
function initAutoRefresh() {
	if (typeof jQuery === 'undefined' && typeof $ === 'undefined') {
		// jQuery not loaded yet, retry
		setTimeout(initAutoRefresh, 50);
		return;
	}
	
	if (typeof window.page_refresh !== 'function') {
		// page_refresh not defined yet, retry
		setTimeout(initAutoRefresh, 50);
		return;
	}
	
	// Both jQuery and page_refresh are available, start auto refresh
	var jq = typeof jQuery !== 'undefined' ? jQuery : $;
	jq(function() {
		// Call immediately on page load to show data right away
		if (typeof window.page_refresh === 'function') {
			window.page_refresh();
		}
		// Then start the auto-refresh cycle
		setTimeout(auto_page_refresh, auto_delay);
	});
}

// Start initialization
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initAutoRefresh);
} else {
	initAutoRefresh();
}

function auto_page_resume()
{
	auto_start_time = new Date().getTime();
	var jq = typeof jQuery !== 'undefined' ? jQuery : $;
	if (jq) {
		jq('#resume_update_button').hide();
	}

	auto_page_refresh();
}

function auto_page_refresh()
{
	if (typeof window.page_refresh === 'function') {
		window.page_refresh();
	}

	var now_time = new Date().getTime();
	if(now_time > auto_start_time + auto_max_time)
	{
		var jq = typeof jQuery !== 'undefined' ? jQuery : $;
		if (jq) {
			jq('#resume_update_button').show();
		}
		//document.title = 'yiimp';
	}
	else
		setTimeout(auto_page_refresh, auto_delay);
}

