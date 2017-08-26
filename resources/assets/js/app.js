
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
let yandexKey = 'trnsl.1.1.20170812T114925Z.1d5a2b5e46b3dc19.6be110234dfafb6f3d1925304f792ea638992fc2';
window.translateYandex = require('yandex-translate')(yandexKey);
window.pluralize = require('pluralize');
window.firework = require('fireworks-effect');

window.Vue = require('vue');
require('vue-resource');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

String.prototype.toHHMM = function (withSeconds) {
    let sec_num = parseInt(this, 10); // don't forget the second param
    let hours = Math.floor(sec_num / 3600);
    let minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    let seconds = sec_num - (hours * 3600) - (minutes * 60);

    if (hours < 10) {
        hours = "0" + hours;
    }
    if (minutes < 10) {
        minutes = "0" + minutes;
    }
    if (seconds < 10) {
        seconds = "0" + seconds;
    }
    let result = hours + ':' + minutes;
    if (withSeconds) {
        result += ':' + seconds;
    }
    return result;
};

String.prototype.startsWithAny = function (searchStrings, position) {
    let string = this.toLowerCase();
    let found = false;
    searchStrings.forEach(searchString => {
        if (string.substr(position || 0, searchString.length) === searchString) {
            found = true;
        }

    });
    return found;
};

String.prototype.trimChars = function(mask) {
    let s = this;
    while (~mask.indexOf(s[0])) {
        s = s.slice(1);
    }
    while (~mask.indexOf(s[s.length - 1])) {
        s = s.slice(0, -1);
    }
    return s.toString();
};

const EventBus = new Vue();

Object.defineProperties(Vue.prototype, {
    $bus: {
        get: function () {
            return EventBus
        }
    }
});

Vue.component('example', require('./components/Example.vue'));
Vue.component('navbar', require('./components/Navbar.vue'));
Vue.component('phrase', require('./components/Phrase.vue'));

// window.Vue.use(require('vue-textarea-autosize'));
window.Vue.use(require('vue-resource'));
window.Vue.use(require('vue-cookie'));
window.Vue.use(require('vue-in-viewport-mixin'));

window.Vue.http.headers.common['X-CSRF-TOKEN'] = document.querySelector('#token').getAttribute('content');

// const app = new Vue({
//     el: '#app'
// });
