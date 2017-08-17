<?php namespace App\Http\Controllers;

use App\Exceptions\UnauthorizedException;
use Illuminate\Http\Request;
use PHPHtmlParser\Dom;

/**
 * Class HomepageController
 * @package App\Http\Controllers\Website
 */
class TranslateController extends Controller
{
    protected $debug = 0;

    protected $token;

    private function getSessionToken()
    {
        $request = app(Request::class);
        if (!$this->token) {
            $this->token = $request->session()->get('session_token');
        }
        return $this->token;
    }

    private function setSessionToken(string $sessionToken)
    {
        $request = app(Request::class);
        $this->token = $sessionToken;
        $request->session()->put('session_token', $this->token);
    }

    public function google(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $origText = $request->get('text');
        $text = str_replace(PHP_EOL, ' ', $origText);
//        $text = str_replace(PHP_EOL, '<br>', $text);
        $encodedText = urlencode($text);
        $response = json_decode(file_get_contents("http://translate.google.com/translate_a/single?client=gtx&ie=UTF-8&oe=UTF-8&sl=$from&tl=$to&dt=t&q=$encodedText&dt=bd&dt=ex&dt=ld&dt=md&dt=qca&dt=rw&dt=rm&dt=ss&dt=t&dt=at"));
//        $response = json_decode(file_get_contents("http://translate.google.com/translate_a/single?client=gtx&sl=$from&tl=$to&dt=t&q=$encodedText"));
        $translation = '';
        foreach ($response[0] as $a) {
            $translation .= $a[0];
        }

        $translation = preg_replace('~<br>\s*~', PHP_EOL, $translation);
        $translation = str_replace(' ...', '...', $translation);

        // if first original is lowercase make 1st translation letter lowercase
        $cleanOrigText = ltrim($origText, '\'');
        preg_match('/^[A-Za-z]/u', $cleanOrigText, $matches);
        $firstIsALetter = array_get($matches, 0);
        if ($firstIsALetter) {
            if ($firstIsALetter && $firstIsALetter === mb_strtolower($firstIsALetter)) {
                $translation = mb_lcfirst($translation);
            } else {
                $translation = mb_ucfirst($translation);
            }
        }
        $translation = preg_replace('/ -([А-Я])/', PHP_EOL . '-$1', $translation);
//        $translation = preg_replace('/\s*$/', '', $translation);
//        $translation = preg_replace('/^- /', '-', $translation);
        if ($request->get('debug')) {
            dd($origText, $text, $translation, $response);
        }

        return response()->json([
            'translation' => $translation,
        ]);
    }

    public function saveApproved(Request $request)
    {
        $jobId = $request->get('jobId');
        $lines = $request->get('lines');
        $isAutosave = $request->get('isAutosave');
        $download = $request->get('download');
        if ($sessionToken = $request->get('sessionToken')) {
            $this->setSessionToken($sessionToken);
        }

        if (!$jobId) {
            throw new \Exception('jobId is missing');
        }

        if (!$lines) {
            throw new \Exception('lines is missing');
        }

        $jobXml = $this->loadAndCache('https://visualdata.sferalabs.com/webservice/jobs/' . $jobId);

        $job = new \DomDocument('1.0', 'utf-8');
        $job->loadXML($jobXml);
        $xpath1 = new \DOMXpath($job);
        if ($node = $xpath1->query('//target_subtitle/url')) {
            $rusSubsUrl = $node->item(0)->nodeValue;
        } else {
            throw new \Exception('cannot find //target_subtitle/url');
        }
        $rusSubs = $this->loadAndCache($rusSubsUrl);

        $doc = new \DomDocument();
        $doc->loadXML($rusSubs);
        $totalCount = 0;
        $translatedClount = 0;
        $lastTime = 0;
        foreach ($doc->getElementsByTagName('p') as $i => $node) {
            /** @var \DOMElement $node */
            $totalCount++;
            $line = array_get($lines, $i);
            if (!$line) {
                continue;
            }
            $translation = '';
            if (array_get($line, 'approveGoogle')) {
                $translation = array_get($line, 'translationGoogle');
            } elseif (array_get($line, 'approveYandex')) {
                $translation = array_get($line, 'translationYandex');
            }
            if (!trim($translation)) {
                continue;
            }

            $lastTime = $node->getAttribute('begin');
            $translatedClount++;
            while ($node->hasChildNodes()) {
                $node->removeChild($node->firstChild);
            }
            $translationLines = explode(PHP_EOL, $translation);
            if (count($translationLines) > 2) {
                $translationLines = [
                    $translationLines[0],
                    implode(' ', array_slice($translationLines, 1))
                ];
            }

            if ($line['isItalic']) {
                $subNode = $doc->createElement("span");
                $subNode->setAttribute('tts:fontStyle', 'italic');
                foreach ($translationLines as $j => $translationLine) {
                    $subNode->appendChild($doc->createTextNode($translationLine));
                    if ($j + 1 != count($translationLines)) {
                        $subNode->appendChild($doc->createElement('br'));
                    }
                }
                $node->appendChild($subNode);
            } else {
                foreach ($translationLines as $j => $translationLine) {
                    $node->appendChild($doc->createTextNode($translationLine));
                    if ($j + 1 != count($translationLines)) {
                        $node->appendChild($doc->createElement('br'));
                    }
                }
            }
        }
        $newXml = $doc->saveXML();
        $newXml = preg_replace('~<p ([^>]*)/>(\s*)$~m', '<p $1></p>$2', $newXml);

        if ($download) {
            $filename = 'translations-' . $request->get('jobId') . '.xml';
            $doc->formatOutput = true;
            $doc->preserveWhiteSpace = false;

            $response = response($newXml, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Filename' => $filename,
            ]);
        } else {

            $this->submitTranslations($jobId, $newXml, $isAutosave, $totalCount, $translatedClount, $lastTime);

            $response = response()->json([
                'success' => true,
            ]);
        }

        return $response;
    }

    public function homepage(Request $request)
    {
        $session = $request->session();
        $token = $session->get('session_token');
        if ($postToken = $request->get('session_token')) {
            $this->setSessionToken($postToken);
            if ($jobId = $request->get('jobId')) {
                return redirect(route('translate', ['jobId' => $jobId]));
            }
            return redirect()->back();
        }

        $jobs = [];
        $jobsXml = $this->loadUrl('https://visualdata.sferalabs.com/webservice/jobs');
        if (!str_contains($jobsXml, 'Unauthorized')) {

            $xml = new \DomDocument('1.0', 'utf-8');
            $xml->loadXML($jobsXml);
            $xpath = new \DOMXpath($xml);

            foreach ($xpath->query('//Job') as $node) {
                /** @var $node \DOMElement */
                $jobs[] = [
                    'id' => $node->getElementsByTagName('id')->item(0)->textContent,
                    'name' => $node->getElementsByTagName('project_name')->item(0)->textContent,
                ];
            }
        }

        return view('homepage', [
            'token' => $token,
            'jobs' => $jobs,
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $jobId = $request->get('jobId');
        if (!$jobId) {
            abort(404, 'must pass ?jobId=');
        }

        $url = 'https://visualdata.sferalabs.com/webservice/jobs/' . $jobId;
        $jobXml = $this->loadAndCache($url);
        if (str_contains($jobXml, 'Unauthorized')) {
            \Session::flash('error', $jobXml);
            $this->forgetCacheFor($url);
            return redirect(route('homepage', [
                'jobId' => $jobId,
            ]));
        }
//        dd($jobXml);

        $xml = new \DomDocument('1.0', 'utf-8');
        $xml->loadXML($jobXml);
        $xpath = new \DOMXpath($xml);

        if ($node = $xpath->query('//source_subtitle/url')->item(0)) {
            $engSubsUrl = $node->nodeValue;
            $rusSubsUrl = $xpath->query('//target_subtitle/url')->item(0)->nodeValue;
            $videoUrl = $xpath->query('//video/url')->item(0)->nodeValue;
        } else {
            throw new \Exception('cannot find //source_subtitle/url');
        }

        $engSubs = $this->loadAndCache($engSubsUrl);
        $rusSubs = $this->loadAndCache($rusSubsUrl, $this->debug ? 120 : 0);
        $translations = $this->getReadyTranslations($rusSubs);

        $dom = new Dom;
        $dom->load($engSubs);

        $lines = [];
        foreach ($dom->find('body div p') as $i => $node) {
            /** @var Dom\HtmlNode $node */
            $line = [];
            $start = $node->getAttribute('begin');
            $end = $node->getAttribute('end');
            $line['start'] = $start;
            $line['end'] = $end;
            $line['secondStart'] = $this->getCurrentTimeDec($start) * 60 - 0.5; // 0.5 sec before
            $line['editable'] = $node->getAttribute('ssroweditable') != 'false';
            $line['html'] = $node->innerHtml();
            $text = strip_tags(str_replace('<br />', PHP_EOL, $line['html']));
            $line['original'] = html_entity_decode(trim($text));
            $line['isItalic'] = str_contains($line['html'], 'tts:fontstyle="italic"');
            $line['translation'] = array_get($translations, $i, '');
            $line['translationYandex'] = '';
            $line['translationGoogle'] = '';
            $line['loadingYandex'] = false;
            $line['loadingGoogle'] = false;
            $line['approveYandex'] = false;
            $line['approveGoogle'] = false;
            $line['index'] = $i + 1;
            $lines[] = $line;
        }

        return view('translate', [
            'lines' => $lines,
            'jobId' => $jobId,
            'videoUrl' => $videoUrl,
            'sessionToken' => $this->getSessionToken(),
            'bannedWords' => config('bannedWords.list'),
        ]);
    }

    private function loadAndCache($url, $minutes = 120)
    {
        $key = 'yulia10.' . md5($url);
        if (!$minutes) {
            return $this->loadUrl($url);
        }
        return \Cache::remember($key, $minutes, function () use ($url) {
            return $this->loadUrl($url);
        });
    }

    private function forgetCacheFor($url)
    {
        $key = 'yulia10.' . md5($url);
        \Cache::forget($key);
    }

    private function loadUrl($url)
    {
        $sessionId = $this->getSessionToken();
        $opts = [
            'http' => [
                'method' => "GET",
                'header' => "Accept-language: en\r\n" .
                    "Cookie: PHPSESSID=$sessionId; ReleaseNotification_sessionID=$sessionId;\r\n",
            ],
        ];

        $context = stream_context_create($opts);
        $content = file_get_contents($url, false, $context);

        return $content;
    }

    private function getReadyTranslations($targetXml)
    {
        $dom = new Dom;
        $dom->load($targetXml);
        $translations = [];
        foreach ($dom->find('body div p') as $i => $node) {
            /** @var Dom\HtmlNode $node */
            $text = $node->innerHtml();
            $text = str_replace('<br />', PHP_EOL, $text);
            $text = strip_tags($text);
            $text = html_entity_decode(trim($text));
            $translations[$i] = $text;
        }
        return $translations;
    }

    public function test(Request $request) {
        echo "<pre>very good!</pre>";
//        print_r($_SERVER);
//        print_r($_POST);
        die();
    }

    private function getCurrentTimeDec($timeString = '00:08:33.931')
    {
        if (preg_match('~(\d\d):(\d\d):(\d\d)\.(\d+)~', $timeString, $matches)) {
            list($all, $hour, $min, $sec, $dec) = $matches;
            $minutes = $hour * 60 + $min;
            $minutesDec = $minutes + ($sec / 60) + round((float)('0.' . $dec) * 1000) / 60 / 1000;
            return $minutesDec;
        }
        return 0;
    }

    public function updateWorklog(Request $request)
    {
        $jobId = $request->get('jobId');

        $url = 'https://visualdata.sferalabs.com/webservice/jobs/updateWorklog?userJobId=' . $jobId;
        if ($this->debug) {
            $url = 'http://yulia-trans.app/test?jobId=' . $jobId;
        }
        $response = $this->sendPost(
            $url,
            [],
            'https://visualdata.sferalabs.com/flex/main?userJobId=' . $jobId
        );

        \Log::debug('updateWorklog sent', [
            'jobId' => $jobId,
            'response' => $response,
        ]);
    }

    public function setUserWorkingActivityStatus(Request $request)
    {
        $jobId = $request->get('jobId');

        $data = [
            'user_job_id' => $jobId,
            'exit_from_flex' => 0,
            'active' => 1,
        ];

        $url = 'https://visualdata.sferalabs.com/webservice/user/setUserWorkingActivityStatus';
        if ($this->debug) {
            $url = 'http://yulia-trans.app/test';
        }
        $response = $this->sendPost(
            $url,
            $data,
            'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf/[[DYNAMIC]]/4'
        );

        \Log::debug('setUserWorkingActivityStatus sent', [
                'request' => $data,
                'response' => $response,
        ]);
    }

    private function submitTranslations($jobId, $xml, $isAutosave, $totalCount, $translatedClount, $lastTime)
    {
        $debug = $this->debug;

        \Log::info('Saving...', ['debug' => $debug, 'isAutosave' => $isAutosave]);

        $data = [
            'userJobId' => $jobId,
            'subtitleContent' => $xml,
            'subtitleType' => 'target',
        ];

        $url1 = 'https://visualdata.sferalabs.com/webservice/simple/save';
        if ($debug) {
            $url1 = 'http://yulia-trans.app/test';
        }

        $response = $this->sendPost($url1, $data, 'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf/[[DYNAMIC]]/4');
        if (str_contains($response, 'Unauthorized')) {
            // "<Response><message>Unauthorized</message><httpStatus>401</httpStatus></Response>"
            \Log::warning('Unauthorized1', [
                'token' => $this->getSessionToken(),
                'response' => $response,
            ]);
            throw new UnauthorizedException('Unauthorized');
        }

        \Log::info('Save1 is done', [
            'response' => $response,
        ]);

        $data = [
            'box_count' => $totalCount,
            'totalCount' => $totalCount,
            'current_box_number' => $translatedClount,
            'secondarySubtitleType' => null,
            'secondarySubtitleContent' => null,
            'isNewlyImported' => 0,
            'subtitleContent' => $xml,
            'userJobId' => $jobId,
            'current_words_number' => 0,
            'subtitleFormat' => 'dfxp',
            'current_box_time' => round($translatedClount * 2.1819, 3),
            'demoMode' => 0,
            'progress' => $translatedClount*100/$totalCount,
            'activeTime' => rand(0, 1),
            'background' => $isAutosave ? 1 : 0,
            'subtitleType' => 'target',
            'current_minute' => $this->getCurrentTimeDec($lastTime),
        ];

        $lentgh = strlen($xml);
        $url2 = 'https://visualdata.sferalabs.com/webservice/jobs/save?contentLength=' . $lentgh . '&secondaryContentLength=0';
        if ($debug) {
            $url2 = 'http://yulia-trans.app/test?contentLength=' . $lentgh;
        }

        $response = $this->sendPost($url2, $data, 'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf');
        if (str_contains($response, 'Unauthorized')) {
            // "<Response><message>Unauthorized</message><httpStatus>401</httpStatus></Response>"
            \Log::warning('Unauthorized2', [
                'token' => $this->getSessionToken(),
                'response' => $response,
            ]);
            throw new UnauthorizedException('Unauthorized');
        }

        \Log::info('Save2 is done', [
            'data' => array_except($data, ['subtitleContent']),
            'response' => $response,
        ]);
    }

    private function sendPost($url, $data, $customRefered = null)
    {
        $sessionId = $this->getSessionToken();
        $referer = $customRefered ?: 'https://visualdata.sferalabs.com/data/flex-app/main/SubtitleApp.swf/[[DYNAMIC]]/4';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Cookie: PHPSESSID={$sessionId}; ReleaseNotification_sessionID={$sessionId}; __utma=121129999.1513241323.1496301115.1502567661.1502607103.111; __utmb=121129999.8.10.1502607103; __utmc=121129999; __utmz=121129999.1496301115.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)",
            "Origin: https://visualdata.sferalabs.com",
            // "Accept-Encoding: gzip, deflate, br",
            "Accept-Language: en-US,en;q=0.8,ru;q=0.6,uk;q=0.4",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: */*",
            "X-Requested-With: ShockwaveFlash/26.0.0.151",
            "Connection: keep-alive",
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $html = curl_exec($ch);
        curl_close($ch);

        return $html;
    }
}
