function initMagnifier(img, glass, zoomLevel = 3) {
    /*create magnifier glass:*/
    glass.style.visibility = "hidden";
    glass.style.display = "none";

    /*insert magnifier glass:*/
    if (img.parentElement) {
        img.parentElement.insertBefore(glass, img);
    }

    /*set background properties for the magnifier glass:*/
    glass.style.backgroundImage = "url('" + img.src + "')";
    glass.style.backgroundRepeat = "no-repeat";
    glass.style.backgroundSize = (img.width * zoomLevel) + "px " + (img.height * zoomLevel) + "px";

    /*execute a function when someone moves the magnifier glass over the image:*/
    glass.addEventListener("mousemove", moveMagnifier);
    img.addEventListener("mousemove", moveMagnifier);

    /*and also for touch screens:*/
    glass.addEventListener("touchmove", moveMagnifier);
    img.addEventListener("touchmove", moveMagnifier);

    function moveMagnifier(e) {
        var pos, x, y;
        /*prevent any other actions that may occur when moving over the image*/
        e.preventDefault();
        /*get the cursor's x and y positions:*/
        pos = getCursorPos(e);
        x = pos.x;
        y = pos.y;

        /*prevent the magnifier glass from being positioned outside the image:*/
        if (x > img.width) {x = img.width;}
        if (x < 0) {x = 0;}
        if (y > img.height) {y = img.height;}
        if (y < 0) {y = 0;}

        /*set the position of the magnifier glass:*/
        glass.style.left = (x - glass.offsetWidth / 2) + "px";
        glass.style.top = (y - glass.offsetHeight / 2) + "px";

        /*display what the magnifier glass "sees":*/
        glass.style.backgroundPosition = "-" + ((x * zoomLevel) - glass.offsetWidth / 2) + "px -" + ((y * zoomLevel) - glass.offsetHeight / 2) + "px";
    }

    function getCursorPos(e) {
        var a, x = 0, y = 0;
        e = e || window.event;
        /*get the x and y positions of the image:*/
        a = img.getBoundingClientRect();
        /*calculate the cursor's x and y coordinates, relative to the image:*/
        x = e.pageX - a.left;
        y = e.pageY - a.top;
        /*consider any page scrolling:*/
        x = x - window.pageXOffset;
        y = y - window.pageYOffset;
        return {x : x, y : y};
    }
}

function initImageZoom() {
    // Find each image container and add magnifier functionality
    const containers = document.querySelectorAll('.image-container');

    containers.forEach((container, index) => {
        const img = container.querySelector('.game-image');
        const magnifierIcon = container.querySelector('.magnifier-icon');
        const glass = container.querySelector('.magnifier-glass');

        if (img && magnifierIcon && glass) {
            // Ensure we reset any previous click handlers
            magnifierIcon.removeEventListener('click', handleMagnifierClick);

            // Add new click handler with the specific img and glass for this container
            magnifierIcon.addEventListener('click', function() {
                handleMagnifierClick.call(this, img, glass);
            });
        }
    });
}

function handleMagnifierClick(img, glass) {
    // Toggle active class
    this.classList.toggle('active');

    if (this.classList.contains('active')) {
        // Update icon
        const iconElement = this.querySelector('i');
        if (iconElement) {
            iconElement.className = 'fas fa-search-minus';
        }

        // Deactivate any other active magnifiers first
        deactivateAllMagnifiers(img);

        // Activate magnifier
        activateMagnifier(img, glass);
    } else {
        // Update icon
        const iconElement = this.querySelector('i');
        if (iconElement) {
            iconElement.className = 'fas fa-search-plus';
        }

        // Deactivate magnifier
        deactivateMagnifier(img, glass);
    }
}

function deactivateAllMagnifiers(exceptImg) {
    const containers = document.querySelectorAll('.image-container');

    containers.forEach(container => {
        const img = container.querySelector('.game-image');
        const magnifierIcon = container.querySelector('.magnifier-icon');
        const glass = container.querySelector('.magnifier-glass');

        if (img && img !== exceptImg && magnifierIcon && glass) {
            if (magnifierIcon.classList.contains('active')) {
                // Update icon
                const iconElement = magnifierIcon.querySelector('i');
                if (iconElement) {
                    iconElement.className = 'fas fa-search-plus';
                }

                // Remove active class
                magnifierIcon.classList.remove('active');

                // Deactivate magnifier
                deactivateMagnifier(img, glass);
            }
        }
    });
}

function activateMagnifier(img, glass) {
    // Initialize the magnifier
    initMagnifier(img, glass, 3);

    // Make glass visible
    glass.style.visibility = "visible";
    glass.style.display = "block";

    // Change cursor
    img.style.cursor = "none";

    // Set a flag to indicate zoom mode is active
    document.body.classList.add('zoom-active');
}

function deactivateMagnifier(img, glass) {
    // Hide glass
    glass.style.visibility = "hidden";
    glass.style.display = "none";

    // Reset cursor
    img.style.cursor = "pointer";

    // Remove event listeners (doesn't work well, better to just hide the glass)
    glass.removeEventListener("mousemove", function() {});
    img.removeEventListener("mousemove", function() {});
    glass.removeEventListener("touchmove", function() {});
    img.removeEventListener("touchmove", function() {});

    // Reset the flag
    document.body.classList.remove('zoom-active');
}

// Initialize zoom when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initImageZoom();
});

// Browser-based JavaScript, no require needed

// Function to show full size image
function showFullSizeImage(imgElement, index) {
    if (!imgElement) return;

    const zoom = mediumZoom(imgElement, {
        margin: 24,
        background: '#000000d9',
        scrollOffset: 0,
        container: {
            width: '100%',
            height: '100%'
        }
    });

    zoom.open();
}

// Make function available globally
window.showFullSizeImage = showFullSizeImage;