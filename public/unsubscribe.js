// ===========================
// START UNSUBSCRIBE FUNCTIONALITY
// ===========================

function showLoader() {
    let loader = document.getElementById('location-check-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'location-check-loader';
        loader.style = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:9999;';
        loader.innerHTML = `
            <div style="text-align:center;">
                <div style="border:8px solid #f3f3f3; border-top:8px solid #0073e6; border-radius:50%; width:60px; height:60px; animation:spin 1s linear infinite; margin:auto;"></div>
                <div style="margin-top:10px;color:#fff;">Please wait...</div>
            </div>
        `;
        const style = document.createElement('style');
        style.innerHTML = `@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}`;
        document.head.appendChild(style);
        document.body.appendChild(loader);
    }
    loader.style.display = 'flex';
}

function hideLoader() {
    const loader = document.getElementById('location-check-loader');
    if (loader) loader.style.display = 'none';
}

async function unsubscribeCustomer(email, shop) {
    console.warn("Let's Start!!");
    if (!email) {
        updateStatusMessage("No email found to unsubscribe.", 'error');
        return;
    }
    showLoader();

    try {
        const url = `/apps/unsubscribe-preference?action=unsubscribe&email=${encodeURIComponent(email)}&shop=${encodeURIComponent(shop)}`;
        const response = await fetch(url);
        const data = await response.json();
        if (data.message) {
            updateStatusMessage(data.message, 'success');
        } else if (data.error) {
            updateStatusMessage("Error: " + data.error, 'error');
        } else {
            updateStatusMessage("Unexpected response from unsubscribe API.", 'error');
        }
    } catch (err) {
        console.error("Error unsubscribing customer:", err);
        updateStatusMessage("Error unsubscribing customer. Please try again later.", 'error');
    } finally {
        hideLoader();
    }
}

(function(){
    console.warn('WORKING MOOD ON!!');
    const urlParams = new URLSearchParams(window.location.search);
    let email = urlParams.get('email');
    if (email) {
        email = email.split('?')[0].trim();
    }

    const shop = window.Shopify && window.Shopify.shop ? window.Shopify.shop : '';
    const isUnsubscribePage = window.location.pathname.includes('/unsubscribe');
    if (isUnsubscribePage) {
        const unsubscribeBtn = document.getElementById('unsubscribeButton');
        if (unsubscribeBtn) {
            unsubscribeBtn.addEventListener('click', function(){
                unsubscribeCustomer(email, shop);
            });
        } else {
            if (email) {
                unsubscribeCustomer(email, shop);
            } else {
                updateStatusMessage("No email provided in URL to unsubscribe.", 'error');
            }
        }
    }
})();

function updateStatusMessage(message, type = 'info') {
    const statusElement = document.getElementById('statusMessage');
    if (statusElement) {
        statusElement.classList.remove('success', 'error', 'warning', 'info');
        statusElement.classList.add(type);
        statusElement.textContent = message;
    } else {
        console.warn('Status message element not found on the page.');
    }
}

// ===========================
// END UNSUBSCRIBE FUNCTIONALITY
// ===========================