
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');
let yandexKey = 'trnsl.1.1.20170812T114925Z.1d5a2b5e46b3dc19.6be110234dfafb6f3d1925304f792ea638992fc2';
window.translateYandex = require('yandex-translate')(yandexKey);
window.pluralize = require('pluralize');

window.googleTranslate = require('google-translator');
window.download = require('download-file');


// window.googleTranslator = new Translator('google');

// translate.translate('You can burn my house, steal my car, drink my liquor from an old fruitjar.', { to: 'ru' }, function(err, res) {
//     console.log(res.text);
// });
//
window.Vue = require('vue');
require('vue-resource');

/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

Vue.component('example', require('./components/Example.vue'));

window.Vue.use(require('vue-textarea-autosize'));
window.Vue.use(require('vue-resource'));

window.Vue.http.headers.common['X-CSRF-TOKEN'] = document.querySelector('#token').getAttribute('content');

// const app = new Vue({
//     el: '#app'
// });
