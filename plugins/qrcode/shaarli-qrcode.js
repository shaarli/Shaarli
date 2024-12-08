/** @licstart  The following is the entire license notice for the
 *  JavaScript code in this page.
 *
 *   Copyright: (c) 2011-2015 Sébastien SAUVAGE <sebsauvage@sebsauvage.net>
 *              (c) 2011-2017 The Shaarli Community, see AUTHORS
 *
 *   This software is provided 'as-is', without any express or implied warranty.
 *   In no event will the authors be held liable for any damages arising from
 *   the use of this software.
 *
 *   Permission is granted to anyone to use this software for any purpose,
 *   including commercial applications, and to alter it and redistribute it
 *   freely, subject to the following restrictions:
 *
 *   1. The origin of this software must not be misrepresented; you must not
 *   claim that you wrote the original software. If you use this software
 *   in a product, an acknowledgment in the product documentation would
 *   be appreciated but is not required.
 *
 *   2. Altered source versions must be plainly marked as such, and must
 *   not be misrepresented as being the original software.
 *
 *   3. This notice may not be removed or altered from any source distribution.
 *
 *  @licend  The above is the entire license notice
 *  for the JavaScript code in this page.
 */

// Show the QR-Code of a permalink (when the QR-Code icon is clicked).
function showQrCode(caller,loading)
{
    // Dynamic javascript lib loading: We only load qr.js if the QR code icon is clicked:
    if (typeof(qr) == 'undefined') // Load qr.js only if not present.
    {
        if (!loading)  // If javascript lib is still loading, do not append script to body.
        {
          var basePath = document.querySelector('input[name="js_base_path"]').value;
          var element = document.createElement("script");
            element.src = basePath + "/plugins/qrcode/qr-1.1.3.min.js";
            document.body.appendChild(element);
        }
        setTimeout(function() { showQrCode(caller,true);}, 200); // Retry in 200 milliseconds.
        return false;
    }

    // Remove previous qrcode if present.
    removeQrcode();

    // Build the div which contains the QR-Code:
    var element = document.createElement('div');
    element.id = 'permalinkQrcode';

	// Make QR-Code div commit sepuku when clicked:
    if (element.addEventListener) {
        element.addEventListener('click', function() {
            this.parentNode.removeChild(this);
        });
    } else if (element.attachEvent) {
        element.attachEvent('onclick', 'this.parentNode.removeChild(this);' );

    } else {
        // Damn IE
        element.setAttribute('onclick', 'this.parentNode.removeChild(this);' );
    }

    // Build the QR-Code:
    var image = qr.image({size: 8,value: caller.dataset.permalink});
    if (image)
    {
        element.appendChild(image);
        element.innerHTML += "<br>Click to close";
        caller.parentNode.appendChild(element);

        // Show the QRCode
        qrcodeImage = document.getElementById('permalinkQrcode');
        // Workaround to deal with newly created element lag for transition.
        window.getComputedStyle(qrcodeImage).opacity;
        qrcodeImage.className = 'show';
    }
    else
    {
        element.innerHTML = "Your browser does not seem to be HTML5 compatible.";
    }
    return false;
}

// Remove any displayed QR-Code
function removeQrcode()
{
    var elem = document.getElementById('permalinkQrcode');
    if (elem) {
        elem.parentNode.removeChild(elem);
    }
    return false;
}

// Create "click" event listeners for QR-Code display action
function setQrCodeClickEvent()
{
    const qrcode_collec = document.getElementsByClassName("qrcode");

    Array.from(qrcode_collec).forEach(function(element) {
        element.addEventListener('click', function() {
            showQrCode(this);
            return false;
        });
    });
}

setQrCodeClickEvent();
