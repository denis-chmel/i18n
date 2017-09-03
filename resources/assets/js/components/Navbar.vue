<template>
    <nav class="navbar-fixed-top" v-bind:class="{ autosave: autosave, 'qa-mode': isQaMode }">
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
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="translateAll(50)">
                                Translate 50
                            </button>
                        </li>
                        <li>
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="saveApproved(0)"
                                :disabled="isSaving"
                            >Save Approved
                            </button>
                        </li>
                        <li>
                            <button type="button"
                                class="btn btn-default navbar-btn"
                                @click="saveApproved(1)"
                                :disabled="isSaving"
                            >Download
                            </button>
                        </li>
                        <li>
                            <label>
                                <input type="checkbox" v-model="autosave" />
                                Autosave &amp; send heartbeat

                                <span v-if="isDebug" class="text-danger">(DEBUG MODE)</span>
                            </label>
                        </li>
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        <li>
                            <i class="fa fa-play" aria-hidden="true" v-if="secondsMoreActive > 0"></i>
                            <input v-if="timer !== undefined" class="timer" readonly type="text"
                                v-bind:value="timer.toString().toHHMM(true)"
                                @click="updateTimer()"
                            >
                            of
                            <input class="timer timer--end"
                                readonly
                                type="text"
                                v-bind:value="etaSeconds.toHHMM()">
                        </li>
                        <li
                            @click="updateBph()"
                            class="boxesPerHour"
                            v-bind:class="{
                                'boxesPerHour--bad': boxesPerHour < bphMin,
                                'boxesPerHour--good': boxesPerHour > bphMax,
                             }"
                        >{{ boxesPerHour }} <abbr>bph</abbr></li>
                        <li class="progress-meter">
                            <span v-if="isQaMode">{{ unprocessedQaCount }} left</span>
                            <span v-if="!isQaMode">{{ Math.round(percentDone * 10) / 10 }}%</span>
                        </li>
                        <li>{{ etaSecondsLeft.toHHMM() }} more</li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</template>

<script>

    module.exports = {
        props: ['subLines', 'percentDone', 'unprocessedQaCount', 'translatedCount', 'jobId', 'isDebug', 'isQaMode'],
        data: function () {
            return {
                bphMin: undefined,
                bphMax: undefined,
                isSaving: false,
                autosave: false,
                timer: undefined,
                secondsMoreActive: 0,
                timerHandle: null,
                _timer1: undefined,
                _timer2: undefined,
                _timer3: undefined,
                _timerActivity: undefined,
            }
        },
        watch: {
            'secondsMoreActive': function () {
                clearTimeout(this._timerActivity);
                if (this.secondsMoreActive > 0) {
                    this._timerActivity = setTimeout(() => {
                        this.timer++;
                        this.storeTimer();
                        this.secondsMoreActive--;
                    }, 1000);
                }
            },
            'autosave': function () {
                if (this.autosave) {
                    let second = this.isDebug ? 100 : 1000;
                    this._timer1 = setInterval(() => {
                        this.saveApproved(0, 1);
                    }, 3 * 60 * second); // each 3 min
                    this._timer2 = setInterval(this.updateWorklog, 60 * second); // each 1 min
                    this._timer3 = setInterval(this.setUserWorkingActivityStatus, 4 * 60 * second); // each 4 min
                } else {
                    clearInterval(this._timer1);
                    clearInterval(this._timer2);
                    clearInterval(this._timer3);
                }
            },
        },
        computed: {
            etaSeconds: function () {
                let seconds = Math.round(this.timer / Math.max(this.percentDone, 1)) * 100;
                seconds = Math.ceil(seconds / 300) * 300;
                return seconds.toString();
            },
            etaSecondsLeft: function () {
                let seconds = Math.max(0, this.etaSeconds - this.timer);
                seconds = Math.floor(seconds / 300) * 300;
                return seconds.toString();
            },
            boxesPerHour: function () {
                let rate = this.translatedCount / (this.timer / 60 / 60);
                return Math.round(rate);
            },
        },
        methods: {
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
                    if (!line.translationAlt.length) {
                        line.translateYandex();
                        found = true;
                    }
                    line.translateReverso();
                    if (!line.translationGoogle.length) {
                        line.translateGoogle(() => {
                            this.translateAll(limit - 1);
                        });
                        found = true;
                    }
                });
            },
            storeTimer: function () {
                this.$cookie.set("timer." + this.jobId, this.timer, 365);
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
            updateTimer: function () {
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
            updateBph: function () {
                let newLimits = prompt(
                    'Enter min-max e.g. 80-100)',
                    this.bphMin + '-' + this.bphMax
                );
                if (newLimits === null) {
                    return;
                }
                this.bphMin = newLimits.split('-')[0];
                this.bphMax = newLimits.split('-')[1];
                this.$cookie.set("bphLimits", newLimits);
            },
            updateWorklog: function () {
                this.$http.post('/updateWorklog', {
                    jobId: this.jobId,
                    debug: this.isDebug,
                }).then((response) => {
                    // nothing
                }).catch((response) => {
                    alert('updateWorklog error:' + response.bodyText);
                });
            },
            setUserWorkingActivityStatus: function () {
                this.$http.post('/setUserWorkingActivityStatus', {
                    jobId: this.jobId,
                    debug: this.isDebug,
                }).then((response) => {
                    // nothing
                }).catch((response) => {
                    alert('setUserWorkingActivityStatus error:' + response.bodyText);
                });
            },
        },
        mounted: function () {
            this.timer = this.$cookie.get("timer." + this.jobId) || 0;

            let limits = this.$cookie.get("bphLimits") || '80-90';
            this.bphMin = limits.split('-')[0];
            this.bphMax = limits.split('-')[1];

            this.$bus.$on('userActive', () => {
                this.secondsMoreActive = 60;
            });
        },
    };

</script>

<style lang="scss" scoped>

    .navbar {
        a {
            cursor: pointer;
            text-decoration: underline;

            &:hover {
                background: #49a5d8 !important;
                color: #FFF !important;
            }
        }
        li {
            padding: 0 1ex;
            line-height: 50px;
        }
    }

    .navbar-fixed-top.autosave {
        .navbar {
            background: #e2ffdd !important;
        }
    }

    .navbar {
        label {
            font-weight: normal;
        }
    }

    .saving-spinner {
        position: absolute;
        top: 8px;
        opacity: 0.3;
    }

    .timer {
        height: 30px;
        width: 80px;
        text-align: center;
        margin-left: 1ex;

        &.timer--end {
            width: 50px;
        }
    }

    .navbar {
        background-color: rgba(255, 255, 255, 0.9);
        border-bottom: 1px solid #e6e6e6;
        padding: 5px;
        box-shadow: 0 0 120px rgba(0, 0, 0, 0.1);
    }

    .navbar-form,
    .form-control:focus {
        border-color: transparent;
        box-shadow: none;
        -webkit-box-shadow: none;
    }

    .form-control:focus {
        border-color: #CCC;
    }

    abbr {
        font-size: 9px;
        text-transform: uppercase;
        border: 1px solid;
        padding: 2px;
        border-radius: 2px;
    }

    .boxesPerHour {
        /*color: #0f98de;*/
    }

    .boxesPerHour--good {
        color: #22a900;
    }

    .boxesPerHour--bad {
        color: #de0060;
    }

    .qa-mode .progress-meter {
        color: #c55fc5;
    }

</style>
