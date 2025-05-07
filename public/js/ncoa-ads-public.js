(function ($) {
   'use strict';

   /**
    * All of the code for your public-facing JavaScript source
    * should reside in this file.
    *
    * Note: It has been assumed you will write jQuery code here, so the
    * $ function reference has been prepared for usage within the scope
    * of this function.
    *
    * This enables you to define handlers, for when the DOM is ready:
    *
    * $(function() {
    *
    * });
    *
    * When the window is loaded:
    *
    * $( window ).load(function() {
    *
    * });
    *
    * ...and/or other possibilities.
    *
    * Ideally, it is not considered best practise to attach more than a
    * single DOM-ready or window-load handler for a particular page.
    * Although scripts in the WordPress core, Plugins and Themes may be
    * practising this, we should strive to set a better example in our own work.
    */

})(jQuery);

// Once ad image loaded, show it on screen, not before.
document.addEventListener("DOMContentLoaded", function () {
   const img = document.querySelector("#ncoadisplay img");

   if (img) {
       img.addEventListener("load", function () {
           img.style.display = "block"; // Show the image after it's loaded
       });

       // If the image is cached and already loaded
       if (img.complete) {
           img.style.display = "block";
       }
   }
});

const ncoa_display = document.getElementById('ncoadisplay');
const timeoutMins = ncoa_display.getAttribute('data-time')
const closeButton = document.getElementById('ncoadisplay-close');
const cookieName = 'ncoadisplay_hide';

function setCookie(name, value, minutes) {
   const date = new Date();
   date.setTime(date.getTime() + (minutes * 60 * 1000));
   const expires = "expires=" + date.toUTCString();
   document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(name) {
   const cookieArr = document.cookie.split(';');
   for (let i = 0; i < cookieArr.length; i++) {
      let cookiePair = cookieArr[i].trimStart();
      if (cookiePair.startsWith(name + "=")) {
         return cookiePair.substring(name.length + 1, cookiePair.length);
      }
   }
   return null;
}

function hideElement() {
   console.log('clicked close');
   ncoa_display.style.display = 'none';
   setCookie(cookieName, 'true', timeoutMins);
}

// Check if the cookie exists on page load
if (getCookie(cookieName) === 'true') {
   ncoa_display.style.display = 'none';
}

closeButton.addEventListener('click', hideElement);