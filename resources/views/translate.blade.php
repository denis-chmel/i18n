@php #
/**
 * @var array $lines
 * @var array $bannedWords
 * @var string $sessionToken
 * @var int $jobId
 * @var boolean $isDebug
 * @var int $untranslatedCount
 */

if ($no = request('box')) {
    $lines = array_slice($lines, $no - 1, 1);
}
// $lines = array_slice($lines, 0, 20);

@endphp

@extends('layout')

@section('contents')

    <nav class="navbar-fixed-top" v-bind:class="{ autosave: autosave }" xmlns:v-bind="http://www.w3.org/1999/xhtml">
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
                            <div v-if="isSaving" class="saving-spinner">
                                <i class="fa-li fa fa-2x fa-cog fa-spin"></i>
                            </div>
                            <button type="button" class="btn btn-default navbar-btn" @click="translateAll(50)">
                                Translate 50
                            </button>
                        </li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved(0)"
                                :disabled="isSaving"
                            >Save Approved</button>
                        </li>
                        <li>
                            <button type="button" class="btn btn-default navbar-btn" @click="saveApproved(1)"
                                :disabled="isSaving"
                            >Download</button>
                        </li>
                        <li>
                            <label>
                                <input type="checkbox" v-model="autosave" @change="toggleTimer()"/>
                                Autosave &amp; send heartbeat

                                @if ($isDebug)
                                    <span class="text-danger">(DEBUG MODE)</span>
                                @endif
                            </label>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li v-if="timer">
                            <i class="fa fa-play" aria-hidden="true" v-if="!timerStarted" @click="startTimer()"></i>
                            <i class="fa fa-pause" aria-hidden="true" v-if="timerStarted" @click="stopTimer()"></i>
                            <input class="timer" readonly type="text"
                              v-bind:value="timer.toString().toHHMM(true)"
                              @click="updateTimer()"
                            >
                            of
                            <input class="timer timer--end" readonly type="text" v-bind:value="getEstimateTime().toHHMM()">
                        </li>
                        <li>@{{ Math.round(percentDone * 10) / 10 }}%</li>
                        <li>@{{ getEstimateTimeLeft().toHHMM() }} left</li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <video data-dashjs-player id="mediaPlayer" controls></video>

    <div class="container">
        <table class="translations">
            <tbody>
                <tr
                    is="phrase"
                    v-for="line in nonCollapsedLines" :key="line.index"
                    :in-viewport-offset-top='1000'
                    :in-viewport-active='viewPortActive'
                    v-bind:line="line"
                    v-on:edited="calculatePercentDone"
                    v-on:reveal-clicked="revealLines"
                ></tr>
            </tbody>
        </table>

        <footer>
            <p>© 2001-2017, Denis feat. Love</p>
        </footer>

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

        String.prototype.toHHMM = function (withSeconds) {
            var sec_num = parseInt(this, 10); // don't forget the second param
            var hours = Math.floor(sec_num / 3600);
            var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
            var seconds = sec_num - (hours * 3600) - (minutes * 60);

            if (hours < 10) {
                hours = "0" + hours;
            }
            if (minutes < 10) {
                minutes = "0" + minutes;
            }
            if (seconds < 10) {
                seconds = "0" + seconds;
            }
            result = hours + ':' + minutes;
            if (withSeconds) {
              result += ':' + seconds;
            }
            return result;
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

        function addExtraMethods(vue, line) {
            line.hasTranslations = function () {
                return (line.translationYandex.length + line.translationGoogle.length > 0);
            };

            line.translateGoogle = function(callback) {
                let line = this;
                if (!line.original.length) {
                    return;
                }
                Vue.set(line, 'loadingGoogle', true);
                Vue.set(line, 'translationGoogle', line.original);

                let original = encodeURI(line.original);
                vue.$http.get('/translate-google?from=en&to=ru&text=' + original).then((response) => {
                    line.translationGoogle = response.body.translation;
                    line.loadingGoogle = false;
                    if (callback) callback();
                });
            };

            line.translateYandex = function(callback) {
                let line = this;
                if (!line.original.length) {
                    return;
                }
                Vue.set(line, 'loadingYandex', true);
                Vue.set(line, 'translationYandex', line.original);
                Vue.set(vue.subLines, 'reload', Math.random());
                window.translateYandex.translate(line.original, { to: 'ru' }, function (err, res) {
                    line.translationYandex = res.text[0];
                    line.loadingYandex = false;
                    if (callback) callback();
                });
            };
        }

        function addLinksToMultitran(line) {

            let words = line.original.match(/\S+/g) || [];

            words.forEach((word, index) => {
                words[index] = trim(word, '.,-!?:');
            });

            let unique = Array.from(new Set(words));

            unique = unique.filter(word => {
                if (word.length < 3) {
                    return;
                }
                if (word.startsWithAny([
                        'you\'', 'it\'', 'can\'', 'we\'', 'haven\''
                    ])) {
                    return;
                }
                return true;
            });

            let html = line.original;

            unique.sort(function (a, b) {
                // ASC  -> a.length - b.length
                // DESC -> b.length - a.length
                return a.length - b.length;
            });

            let target = '{{ uniqid() }}';
            unique.forEach((word) => {
                let singular = window.pluralize.singular(word);
                if (bannedWords.includes(singular.toLowerCase())) {
                    return;
                }
                let regex = new RegExp('\\b' + word + '\\b');
                html = html.replace(regex, `<a target="${target}" tabindex="-1" href="https://www.multitran.ru/c/m.exe?s=${encodeURIComponent(singular)}">${word}</a>`);
            });
            line.html = html.replace(/\n/, '<span class="enter"></span>\n');
        }

        const app = new Vue({
            el: '#app',
            data: {
                timer: undefined,
                timerStarted: false,
                viewPortActive: true,
                timerHandle: null,
                isSaving: false,
                percentDone: 0,
                jobId: {{ $jobId }},
                isDebug: {{ (int)$isDebug }},
                autosave: false,
                videoUrl: {!! j($videoUrl) !!},
                subLines: {!! j($lines) !!},
            },
            computed: {
                nonCollapsedLines: function() {
                    let result = [];
                    let prevLine = null;
                    this.subLines.forEach(function(line){
                        if (line.collapsed) {
                            if (!prevLine || !prevLine.collapsed) {
                                result.push(line);
                            }
                        } else {
                            result.push(line);
                        }
                        prevLine = line;
                    });
                    let prevIndex = null;
                    result.forEach(function(line, index) {
                        if (result[index + 1]) {
                            line.nextLineIndex = result[index + 1].index;
                        }
                    });
                    result.forEach(function(line, index) {
                      // do not collapse line if it's the only one
                        if (line.nextLineIndex == line.index + 1) {
                            line.collapsed = false;
                        }
                    });
                    return result;
                },
            },
            watch: {
                'timerStarted': function (timerStarted) {
                    if (timerStarted) {
                        this.timerHandle = setInterval(() => {
                            this.timer++;
                            this.storeTimer();
                        }, 1000);
                    } else {
                        clearInterval(this.timerHandle);
                    }
                }
            },
            methods: {
              storeTimer: function(){
                this.$cookie.set("timer." + this.jobId, this.timer, 365);
              },
                updateTimer: function() {
                  let newTime = prompt(
                    'Enter time taken in hours (e.g. 6.5)',
                    Math.round(this.timer / 60 / 6) / 10
                  );
                  if (newTime === null) {
                    return;
                  }
                  this.timer = parseFloat(newTime) * 60 * 60;
                  this.storeTimer();
                },
                getEstimateTime: function(){
                    return (Math.round(this.timer / this.percentDone / 10) * 1000).toString();
                },
                getEstimateTimeLeft: function(){
                    return (Math.round(this.timer * (1 / this.percentDone * 100 - 1))).toString();
                },
                secondsToTime: function (seconds) {
                    return seconds;
                },
                startTimer: function() {
                    if (!this.autosave) {
                        this.timerStarted = true;
                    }
                },
                stopTimer: function() {
                    if (!this.autosave) {
                        this.timerStarted = false;
                    }
                },
                toggleTimer: function() {
                    this.timerStarted = this.autosave;
                },
                translateAll: function (limit) {
                    if (limit === undefined) {
                        limit = 50;
                    }
                    let found = false;
                    this.subLines.forEach((line) => {
                        if (found || limit <= 0) {
                            return;
                        }
                        if (!line.original.length) {
                            return;
                        }
                        if (!line.editable) {
                            return;
                        }
                        if (line.hasTranslations()) {
                            return;
                        }
                        if (!line.translationYandex.length) {
                            line.translateYandex();
                            found = true;
                        }
                        if (!line.translationGoogle.length) {
                            line.translateGoogle(() => {
                                this.translateAll(limit - 1);
                            });
                            found = true;
                        }
                    });
                },
                calculatePercentDone: function () {
                    let translated = this.subLines.filter(line => {
                        return line.approveYandex || line.approveGoogle ? line : false;
                    });
                    let percent = translated.length * 100 / this.subLines.length;
                    Vue.set(this, 'percentDone', percent);
                    @if ($untranslatedCount)
                    if (percent === 100) {
                        if (!$('canvas').length) {
                            window.firework.start({ autoPlay: true });
                            window.firework.fireworks();
                            setTimeout(()=>{
                                $('canvas').closest('div').fadeOut(5000, function(){
                                    $('canvas').closest('div').remove();
                                });
                            }, 60000);
                        }
                    }
                    @endif
                },
                saveApproved: function (download, isAutosave, sessionToken) {
                    this.isSaving = true;
                    this.$http.post('/save-approved', {
                        lines: this.subLines,
                        jobId: this.jobId,
                        download: download,
                        debug: this.isDebug,
                        isAutosave: isAutosave,
                        sessionToken: sessionToken
                    }).then((response) => {
                        if (map = response.headers.map['content-filename']) {
                            let headers = {};
                            let blob = new Blob([response.data], { type: headers['content-type'] });
                            let link = document.createElement('a');
                            link.href = window.URL.createObjectURL(blob);
                            link.download = map[0];
                            link.click();
                        } else if (!isAutosave) {
                            console.error(response);
                            alert("Failed to save, ask Denis to check logs for " + (new Date()));
                        }
                    }).finally((response) => {
                        this.isSaving = false;
                    }).catch((response) => {
                        if (response.body.error === "Unauthorized") {
                            let sessionToken = prompt('Save has failed. Enter new PHPSESSID', '{{ $sessionToken }}');
                            if (sessionToken) {
                                this.saveApproved(download, 0, sessionToken);
                            }
                        } else {
                            alert(response.bodyText);
                        }
                    });
                },
                autosaveIfNeeded: function () {
                    if (this.autosave) {
                        this.saveApproved(0, 1);
                    }
                },
                updateWorklog: function () {
                    if (this.autosave) {
                        this.$http.post('/updateWorklog', {
                            jobId: this.jobId,
                            debug: this.isDebug,
                        }).then((response) => {
                            // nothing
                        }).catch((response) => {
                            alert('updateWorklog error:' + response.bodyText);
                        });
                    }
                },
                setUserWorkingActivityStatus: function () {
                    if (this.autosave) {
                        if (this.autosave) {
                            this.$http.post('/setUserWorkingActivityStatus', {
                                jobId: this.jobId,
                                debug: this.isDebug,
                            }).then((response) => {
                                // nothing
                            }).catch((response) => {
                                alert('setUserWorkingActivityStatus error:' + response.bodyText);
                            });
                        }
                    }
                },
                revealLines: function(line) {
                    Vue.set(this, 'viewPortActive', false);
                    for (let i = line.index - 1; i < line.nextLineIndex - 1; i++) {
                        this.subLines[i].collapsed = false;
                    }
                    setTimeout(()=>{
                        Vue.set(this, 'viewPortActive', true);
                    });
                },
                playPhrase: function (line) {
                    window.mediaPlayer.play();
                    window.mediaPlayer.seek(line.secondStart);
                }
            },
            mounted: function () {

                this.timer = this.$cookie.get("timer." + this.jobId) || 0;

                this.subLines.forEach(line => {

                    if (line.translation.length) {
                        line.translationGoogle = line.translation;
                        line.approveGoogle = true;
                    }

                    addLinksToMultitran(line);
                    addExtraMethods(this, line);

                    this.calculatePercentDone();
                });

                let second = {{ $isDebug ? 100 : 1000 }};
                setInterval(this.autosaveIfNeeded, 3 * 60 * second); // each 3 min
                setInterval(this.updateWorklog, 60 * second); // each 1 min
                setInterval(this.setUserWorkingActivityStatus, 4 * 60 * second); // each 4 min

                setTimeout(() => {
                    startVideo(this.videoUrl);
                }, 2000);
            }
        });


        function startVideo(url) {
            window.mediaPlayer = dashjs.MediaPlayer().create();
            window.mediaPlayer.getDebug().setLogToBrowserConsole(false);
            window.mediaPlayer.initialize(document.querySelector("#mediaPlayer"), url, false);
        }

    </script>
    <script src="http://cdn.dashjs.org/latest/dash.all.min.js"></script>
@append
