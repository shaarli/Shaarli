// Remove any displayed QR-Code
function remove_qrcode()
{ 
    var elem = document.getElementById("permalinkQrcode");
    if (elem) elem.parentNode.removeChild(elem);
    return false;
}

// Show the QR-Code of a permalink (when the QR-Code icon is clicked).
function showQrCode(caller,loading=false)
{ 
    // Dynamic javascript lib loading: We only load qr.js if the QR code icon is clicked:
    if (typeof(qr)=='undefined') // Load qr.js only if not present.
    {
        if (!loading)  // If javascript lib is still loading, do not append script to body.
        {
            var element = document.createElement("script");
            element.src = "inc/qr.min.js";
            document.body.appendChild(element);
        }
        setTimeout(function() { showQrCode(caller,true);}, 200); // Retry in 200 milliseconds.
        return false;
    }

    // Remove previous qrcode if present.
    remove_qrcode();
    
    // Build the div which contains the QR-Code:
    var element = document.createElement('div');
    element.id="permalinkQrcode";
	// Make QR-Code div commit sepuku when clicked:
    if ( element.attachEvent ){ element.attachEvent('onclick', 'this.parentNode.removeChild(this);' ); } // Damn IE
    else { element.setAttribute('onclick', 'this.parentNode.removeChild(this);' ); }
    
    // Build the QR-Code:
    var image = qr.image({size: 8,value: caller.dataset.permalink});
    if (image)
    { 
        element.appendChild(image);
        element.innerHTML+= "<br>Click to close";
        caller.parentNode.appendChild(element);
    }
    else
    {
        element.innerHTML="Your browser does not seem to be HTML5 compatible.";
    }
    return false;
}
