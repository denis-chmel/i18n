@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var int $jobId
 */

$lines = array_slice($lines, 0, 20);

@endphp

@extends('layout')

@section('contents')

    <nav id='nav_bar'>
        <div class="navbar navbar-default navbar-static">
            <div class="container">
                <!-- .btn-navbar is used as the toggle for collapsed navbar content -->
                <a class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="glyphicon glyphicon-bar"></span>
                    <span class="glyphicon glyphicon-bar"></span>
                    <span class="glyphicon glyphicon-bar"></span>
                </a>
                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav">
                        <li>
                            <button type="button" class="btn btn-primary navbar-btn" @click="translateAll()">Translate All</button>
                        </li>
                        <li class="divider">&nbsp;&nbsp;</li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="exportAll()">Download</button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
    <table class="translations">

        <tbody>
        <tr valign="top" v-for="entry in subLines">
            <td>
                @{{entry['begin']}}<br>
                @{{entry['end']}}<br>
            </td>
            <td>
                <pre v-html="entry['html']"></pre>
            </td>
            <td>
                <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateYandex(entry)">Я</button>
                <textarea-autosize
                    @click.native="approveYandex(entry)"
                    v-bind:class="{ loading: entry.loadingYandex, approved: entry.approveYandex }"
                    v-model="entry['translationYandex']"
                ></textarea-autosize>
            </td>
            <td>
                <button tabindex="-1" class="btn btn-default btn-xs" type="button" @click="translateGoogle(entry)">G</button>
                <textarea-autosize
                    @click.native="approveGoogle(entry)"
                    v-bind:class="{ loading: entry.loadingGoogle, approved: entry.approveGoogle }"
                    v-model="entry['translationGoogle']"
                ></textarea-autosize>
            </td>
        </tr>
        </tbody>

    </table>
    </div>

@endsection

@section('footer-scripts')
    <script type="text/javascript">

        let bannedWords = {!! json_encode($bannedWords) !!};

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

        function trim(s, mask) {
            while (~mask.indexOf(s[0])) {
                s = s.slice(1);
            }
            while (~mask.indexOf(s[s.length - 1])) {
                s = s.slice(0, -1);
            }
            return s;
        }

        const app = new Vue({
            el: '#app',
            data: {
                subLines: {!! j($lines) !!}
            },
            methods: {
                translateYandex: function (line, delay) {
                    Vue.set(line, 'loadingYandex', true);
                    Vue.set(line, 'translationYandex', line.original);
                    Vue.set(this.subLines, 'reload', Math.random());
                    setTimeout(a => {
                        window.translateYandex.translate(line.text, { to: 'ru' }, function (err, res) {
                            line.translationYandex = res.text[0];
                            line.loadingYandex = false;
                        });
                    }, delay || 200);
                },
                approveYandex: function (line) {
                    Vue.set(line, 'approveYandex', true);
                    Vue.set(line, 'approveGoogle', false);
                },
                approveGoogle: function (line) {
                    Vue.set(line, 'approveYandex', false);
                    Vue.set(line, 'approveGoogle', true);
                },
                translateGoogle: function (line, delay) {
                    Vue.set(line, 'loadingGoogle', true);
                    Vue.set(line, 'translationGoogle', line.original);
                    setTimeout(a => {

                        window.googleTranslate('en', 'ru', line.text, response => {
                            response = JSON.parse(response);
                            line.translationGoogle = response.translation;
                            line.loadingGoogle = false;
                        });

                    }, delay || 0);
                },
                translateAll: function () {
                    this.subLines.forEach((line, index) => {
                        line.approveYandex = true;
                        line.approveGoogle = false;
                        this.translateYandex(line, index * 200);
                        this.translateGoogle(line, index * 200);
                    });
                },
                translateAllYandex: function () {
                    this.subLines.forEach((line, index) => {
                        this.translateYandex(line, index * 200);
                    });
                },
                translateAllGoogle: function () {
                    this.subLines.forEach((line, index) => {
                        this.translateGoogle(line, index * 200);
                    });
                },
                exportAll: function () {
                    this.$http.post('/export-all', {
                        lines: this.subLines,
                        jobId: {{ $jobId }},
                    }).then((response) => {
                        let headers = {};
                        let blob = new Blob([response.data], { type: headers['content-type'] });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = response.headers.map['content-filename'][0];
                        link.click();
                    });
                },
            },
            mounted: function () {
                this.subLines.forEach(line => {

                    let words = line.text.match(/\S+/g);

                    words.forEach((word, index) => {
                        words[index] = trim(word, '.,-!?:');
                    });

                    let unique = Array.from(new Set(words));

                    unique = unique.filter(word => {
                        if (word.length < 4) {
                            return;
                        }
                        if (word.startsWithAny([
                                'you\'', 'it\'', 'can\''
                            ])) {
                            return;
                        }
                        return true;
                    });

                    let html = line.text;

                    unique.forEach((word) => {
                        let singular = window.pluralize.singular(word);
                        if (bannedWords.includes(singular.toLowerCase())) {
                            return;
                        }
                        word = trim(word, '.,-');
                        html = html.replace(word, `<a target="_blank" tabindex="-1" href="https://www.multitran.ru/c/m.exe?s=${encodeURIComponent(singular)}">${word}</a>`);
                    });
                    line.html = html;
                });
            }
        });


        $(document).ready(function () {
            //change the integers below to match the height of your upper dive, which I called
            //banner.  Just add a 1 to the last number.  console.log($(window).scrollTop())
            //to figure out what the scroll position is when exactly you want to fix the nav
            //bar or div or whatever.  I stuck in the console.log for you.  Just remove when
            //you know the position.
            $(window).scroll(function () {

                if ($(window).scrollTop() > 250) {
                    $('#nav_bar').addClass('navbar-fixed-top');
                }

                if ($(window).scrollTop() < 251) {
                    $('#nav_bar').removeClass('navbar-fixed-top');
                }
            });
        });

    </script>
    <style>
    </style>
@append
