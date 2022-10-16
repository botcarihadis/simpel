<?php 
// persiapan
init();

// jika user mengirim /start
$bot->cmd('/start','Silahkan kirim kalimat yang ingin Anda cari');

// jika user mengirim kalimat tertentu
$bot->on('text',function($pesan){
    
    //ambil kalimat yang dicari
    $q = urlencode(trim($pesan));

    // cari harokat kalau ada
    $find = array("َ","ِ","ُ","ً","ٍ","ٌ","ْ","ّ");

    // hapus harokat
    $q = str_replace($find,"",$q);
  
    // jika tidak ada kalimat yang dicari
    if(empty($q))
    
    // kirim pesan balasan
    return Bot::sendMessage("Teks kosong",reply());
  
    // jika ada kalimat yang dicari, kirim efek mengetik
    typing();
    
    // kirim pesan mohon tunggu
    $message_id = json_decode(Bot::sendMessage('Mohon tunggu sebentar, kami sedang mencari...',reply()),true)['result']['message_id'];
  
    // ambil dari carihadis.com
    $json = file_get_contents('http://api2.carihadis.com/?q='.urlencode($q));
  
    // olah hasil menjadi array
    $isi = json_decode($json,true);
  
    //jika pengolahan selesai, kirim efek mengetik
    typing();

    // jika hasilnya kosong
    if($isi['data'] == null) {

        // edit pesan sebelumnya
        return Bot::editMessageText(['message_id'=>$message_id,'text'=>'Maaf, kami tidak berhasil menemukan. Silahkan coba kalimat lain'],reply());  
    }
  
    // jika hasil tidak kosong, siapkan hasil yang mau dikirim  
    $hasil = '';
  
    // olah hasil menjadi pesan
    foreach($isi as $data){
        foreach($data as $i=>$a){
            $ar = $a['id'];
            foreach($ar as $id){
                $hasil .= '/'.$a['kitab'].$id."\n";
            }
            
        }
    }
  
    $hasil = "Ditemukan hasil sebagai berikut:\n\n$hasil";
  
    // jika panjang pesan melebihi batas maksimal Telegram
    if(strlen($hasil) > 4096 ){
        // edit pesan sebelumnya
        return Bot::editMessageText(['message_id'=>$message_id,'text'=>"Hasil terlalu banyak, silahkan cari kata atau kalimat yang lebih spesifik, atau lihat versi <a href='https://carihadis.com/?teks=$q'>web</a>.",'reply'=>true,'parse_mode'=>'html']);
    }
            
    
    // jika panjang pesan tidak melebihi batas maksimal Telegram, kirim efek mengetik
    typing();

    // edit pesan balasan ke user  
    return Bot::editMessageText(['message_id'=>$message_id,'text'=>$hasil,'reply'=>true]);
});

// jika user mengirim format /Nama_Kitab123
$bot->regex('/^\/([a-zA-Z_]+)(\d+)(\@.+bot)?$/i',function($cocok){

    // ambil nama kitab
	$kitab = cukur($cocok[1]);
    // ambil id
	$id = $cocok[2];
	
    // request ke carihadis
	$hasil = file_get_contents("http://api2.carihadis.com/?kitab=$kitab&id=$id");
	
    // urai respon dari carihadis
	$hasil = json_decode($hasil,true);
    
    // jika nama kitab salah atau nomor hadis tidak ada
    if(empty($hasil['data'])) return Bot::sendMessage('Link tidak valid',reply());
    
    // ganti garis bawah dengan spasi
    $ref = str_replace('_',' ',$kitab);

    // siapkan link
    $ref = "<a href='https://carihadis.com/$kitab/$id'>$ref $id</a>";
  
	// bersihkan tag HTML dari teks Arab dan terjemahnya
    $nass = strip_tags($hasil['data'][1]['nass']);
	$terjemah = strip_tags($hasil['data'][1]['terjemah']);
    $terjemah = str_replace(']','</b>',$terjemah);
    $terjemah = str_replace('[','<b>',$terjemah);
    $pesan = "$ref\n\n$nass\n\n$terjemah";
  
    // jika panjang melebihi batas maksimal Telegram
    if(strlen($pesan) > 4096 ){
        
        // potong pesan
        $pesan = potong($pesan,4096);
        
        // siapkan tombol 'selengkapnya'
        $keyboard[] = [
            ['text' => 'Lihat selengkapnya', 'url' => "https://carihadis.com/$kitab/$id"],
        ];
        
        $options = [
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'reply'=>true,
            'parse_mode'=>'html'
        ];
        
        // kirim pesan balasan disertai tombol
        return Bot::sendMessage($pesan[0],$options);
    }
    
    // jika panjang pesan tidak melebihi batas maksimal, kirim efek mengetik
    typing();

    // kirim balasan ke user
	return Bot::sendMessage($pesan,['reply'=>true,'parse_mode'=>'html']);
});

// jika user mengirim teks bebas
//$bot->cmd('*','Kirim kalimat yang ingin dicari');

// jika user mengirim apa saja 
//$bot->on('*','Kirim kalimat yang ingin dicari');

// jalankan bot
$bot->run();

// kumpulan function
function reply(){
  return ['reply'=>true];
}

function typing(){
  return Bot::sendChatAction('',['action'=>'typing']);
}
function cukur($a){
  if(strrpos($a,'_') == strlen($a) - 1 ) { 
    $a = substr($a,0,-1);
    $a = cukur($a);
  }
  if(strpos($a,'_') === 0){
    $a = substr($a,1);
    $a = cukur($a);
  }
  return $a;
}

function pecah($text,$jml_kar){
    $karakter = $text[$jml_kar];
    while($karakter != ' ' AND $karakter != "\n" AND $karakter != "\r" AND $karakter != "\r\n") {//kalau bukan spasi atau new line
        $karakter = $text[--$jml_kar];//cari spasi sebelumnya
    }
    $pecahan = substr($text, 0, $jml_kar);
    return trim($pecahan);
}

function potong($text,$jml_kar){
    $panjang = strlen($text);
    $ke = 0;
    $pecahan = [];
    while($panjang>$jml_kar){
        $pecahan[] = pecah($text,$jml_kar);//str
        $panjang = strlen($pecahan[$ke]);//int
        $text = trim(substr($text,$panjang));//str
        $panjang = strlen($text);//int
        $ke++;//int
    }
    $array = array_merge($pecahan, array($text));
    return $array;
}

function init(){
  require __DIR__.'/token.php';
  $GLOBALS['bot'] = new PHPTelebot($token_bot, $username_bot);
}

/**
 * PHPTelebot.php.
 *
 *
 * @author Radya <radya@gmx.com>
 *
 * @link https://github.com/radyakaze/phptelebot
 *
 * @license GPL-3.0
 */

/**
 * Class PHPTelebot.
 */
class PHPTelebot
{
    /**
     * @var array
     */
    public static $getUpdates = [];
    /**
     * @var array
     */
    protected $_command = [];
    /**
     * @var array
     */
    protected $_onMessage = [];
    /**
     * Bot token.
     *
     * @var string
     */
    public static $token = '';
    /**
     * Bot username.
     *
     * @var string
     */
    protected static $username = '';

    /**
     * Debug.
     *
     * @var bool
     */
    public static $debug = true;

    /**
     * PHPTelebot version.
     *
     * @var string
     */
    protected static $version = '1.3';

    /**
     * PHPTelebot Constructor.
     *
     * @param string $token
     * @param string $username
     */
    public function __construct($token, $username = '')
    {
        // Check php version
        if (version_compare(phpversion(), '5.4', '<')) {
            die("PHPTelebot needs to use PHP 5.4 or higher.\n");
        }

        // Check curl
        if (!function_exists('curl_version')) {
            #die("cURL is NOT installed on this server.\n"); # sudah dihandle oleh file_get_contents
        }

        // Check bot token
        if (empty($token)) {
            die("Bot token should not be empty!\n");
        }

        self::$token = $token;
        self::$username = $username;
    }

    /**
     * Command.
     *
     * @param string          $command
     * @param callable|string $answer
     */
    public function cmd($command, $answer)
    {
        if ($command != '*') {
            $this->_command[$command] = $answer;
        }

        if (strrpos($command, '*') !== false) {
            $this->_onMessage['text'] = $answer;
        }
    }
    /**
     * Events.
     *
     * @param string          $types
     * @param callable|string $answer
     */
    public function on($types, $answer)
    {
        $types = explode('|', $types);
        foreach ($types as $type) {
            $this->_onMessage[$type] = $answer;
        }
    }

    /**
     * Custom regex for command.
     *
     * @param string          $regex
     * @param callable|string $answer
     */
    public function regex($regex, $answer)
    {
        $this->_command['customRegex:'.$regex] = $answer;
    }

    /**
     * Run telebot.
     *
     * @return bool
     */
    public function run()
    {
        try {
            if (php_sapi_name() == 'cli') {
                echo 'PHPTelebot version '.self::$version;
                echo "\nMode\t: Long Polling\n";
                $options = getopt('q', ['quiet']);
                if (isset($options['q']) || isset($options['quiet'])) {
                    self::$debug = false;
                }
                echo "Debug\t: ".(self::$debug ? 'ON' : 'OFF')."\n";
                $this->longPoll();
            } else {
                $this->webhook();
            }

            return true;
        } catch (Exception $e) {
            echo $e->getMessage()."\n";

            return false;
        }
    }

    /**
     * Webhook Mode.
     */
    private function webhook()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['CONTENT_TYPE'] == 'application/json') {
            self::$getUpdates = json_decode(file_get_contents('php://input'), true);
            echo $this->process();
        } else {
            http_response_code(400);
            throw new Exception('Access not allowed!');
        }
    }

    /**
     * Long Poll Mode.
     *
     * @throws Exception
     */
    private function longPoll()
    {
        $offset = 0;
        while (true) {
            $req = json_decode(Bot::send('getUpdates', ['offset' => $offset, 'timeout' => 30]), true);

            // Check error.
            if (isset($req['error_code'])) {
                if ($req['error_code'] == 404) {
                    $req['description'] = 'Incorrect bot token';
                }
                throw new Exception($req['description']);
            }

            if (!empty($req['result'])) {
                foreach ($req['result'] as $update) {
                    self::$getUpdates = $update;
                    $process = $this->process();

                    if (self::$debug) {
                        $line = "\n--------------------\n";
                        $outputFormat = "$line %s $update[update_id] $line%s";
                        echo sprintf($outputFormat, 'Query ID :', json_encode($update));
                        echo sprintf($outputFormat, 'Response for :', Bot::$debug?: $process ?: '--NO RESPONSE--');
                        // reset debug
                        Bot::$debug = '';
                    }
                    $offset = $update['update_id'] + 1;
                }
            }

            // Delay 1 second
            sleep(1);
        }
    }

    /**
     * Process the message.
     *
     * @return string
     */
    private function process()
    {
        $get = self::$getUpdates;
        $run = false;

        if (isset($get['message']['date']) && $get['message']['date'] < (time() - 120)) {
            return '-- Pass --';
        }

        if (Bot::type() == 'text') {
            $customRegex = false;
            foreach ($this->_command as $cmd => $call) {
                if (substr($cmd, 0, 12) == 'customRegex:') {
                    $regex = substr($cmd, 12);
                    // Remove bot username from command
                     if (self::$username != '') {
                         $get['message']['text'] = preg_replace('/^\/(.*)@'.self::$username.'(.*)/', '/$1$2', $get['message']['text']);
                     }
                    $customRegex = true;
                } else {
                    $regex = '/^(?:'.addcslashes($cmd, '/\+*?[^]$(){}=!<>:-').')'.(self::$username ? '(?:@'.self::$username.')?' : '').'(?:\s(.*))?$/';
                }
                if ($get['message']['text'] != '*' && preg_match($regex, $get['message']['text'], $matches)) {
                    $run = true;
                    if ($customRegex) {
                        $param = [$matches];
                    } else {
                        $param = isset($matches[1]) ? $matches[1] : '';
                    }
                    break;
                }
            }
        }

        if (isset($this->_onMessage) && $run === false) {
            if (in_array(Bot::type(), array_keys($this->_onMessage))) {
                $run = true;
                $call = $this->_onMessage[Bot::type()];
            } elseif (isset($this->_onMessage['*'])) {
                $run = true;
                $call = $this->_onMessage['*'];
            }

            if ($run) {
                switch (Bot::type()) {
                    case 'callback':
                        $param = $get['callback_query']['data'];
                    break;
                    case 'inline':
                        $param = $get['inline_query']['query'];
                    break;
                    case 'location':
                        $param = [$get['message']['location']['longitude'], $get['message']['location']['latitude']];
                    break;
                    case 'text':
                        $param = $get['message']['text'];
                    break;
                    default:
                        $param = '';
                    break;
                }
            }
        }

        if ($run) {
            if (is_callable($call)) {
                if (!is_array($param)) {
                    $count = count((new ReflectionFunction($call))->getParameters());
                    if ($count > 1) {
                        $param = array_pad(explode(' ', $param, $count), $count, '');
                    } else {
                        $param = [$param];
                    }
                }

                return call_user_func_array($call, $param);
            } else {
                if (!isset($get['inline_query'])) {
                    return Bot::send('sendMessage', ['text' => $call]);
                }
            }
        }
    }
}

/**
 * Bot.php.
 *
 *
 * @author Radya <radya@gmx.com>
 *
 * @link https://github.com/radyakaze/phptelebot
 *
 * @license GPL-3.0
 */

/**
 * Class Bot.
 */
class Bot
{
    /**
     * Bot response debug.
     * 
     * @var string
     */
    public static $debug = '';

    /**
     * Send request to telegram api server.
     *
     * @param string $action
     * @param array  $data   [optional]
     *
     * @return array|bool
     */
    public static function send($action = 'sendMessage', $data = [])
    {
        $upload = false;
        $actionUpload = ['sendPhoto', 'sendAudio', 'sendDocument', 'sendSticker', 'sendVideo', 'sendVoice'];

        if (in_array($action, $actionUpload)) {
            $field = str_replace('send', '', strtolower($action));

            if (is_file($data[$field])) {
                $upload = true;
                $data[$field] = self::curlFile($data[$field]);
            }
        }

        $needChatId = ['sendMessage', 'forwardMessage', 'sendPhoto', 'sendAudio', 'sendDocument', 'sendSticker', 'sendVideo', 'sendVoice', 'sendLocation', 'sendVenue', 'sendContact', 'sendChatAction', 'editMessageText', 'editMessageCaption', 'editMessageReplyMarkup', 'sendGame'];
        if (in_array($action, $needChatId) && !isset($data['chat_id'])) {
            $getUpdates = PHPTelebot::$getUpdates;
            if (isset($getUpdates['callback_query'])) {
                $getUpdates = $getUpdates['callback_query'];
            }
            $data['chat_id'] = $getUpdates['message']['chat']['id'];
            // Reply message
            if (!isset($data['reply_to_message_id']) && isset($data['reply']) && $data['reply'] === true) {
                $data['reply_to_message_id'] = $getUpdates['message']['message_id'];
                unset($data['reply']);
            }
        }

        if (isset($data['reply_markup']) && is_array($data['reply_markup'])) {
            $data['reply_markup'] = json_encode($data['reply_markup']);
        }

# tambahan danns mulai
if (function_exists('curl_version')) {
# tambahan danns selesai

        $ch = curl_init();
        $options = [
            CURLOPT_URL => 'https://api.telegram.org/bot'.PHPTelebot::$token.'/'.$action,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        if (is_array($data)) {
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        if ($upload !== false) {
            $options[CURLOPT_HTTPHEADER] = ['Content-Type: multipart/form-data'];
        }

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            echo curl_error($ch)."\n";
        }
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

# tambahan danns mulai        
}else{
  $url = 'https://api.telegram.org/bot'.PHPTelebot::$token.'/'.$action;
  
  if (is_array($data)) {
    $data = $data;
  }

$data = http_build_query($data);

# header
if ($upload !== false) {
  $header = 'Content-Type: multipart/form-data';
}else{
  $header = 'Content-Type: application/x-www-form-urlencoded';
}

$opts=[
'http'=>[
'method'=>"POST",
'header'=>$header,
'content'=>$data
   ]
];

$context=stream_context_create($opts);

$result=file_get_contents($url,false,$context);
$httpcode = null;
}
#tambahan danns selesai

        if (PHPTelebot::$debug && $action != 'getUpdates') {
            self::$debug .= 'Method: '.$action."\n";
            self::$debug .= 'Data: '.str_replace("Array\n", '', print_r($data, true))."\n";
            self::$debug .= 'Response: '.$result."\n";
        }

        if ($httpcode == 401) {
            throw new Exception('Incorect bot token');

            return false;
        } else {
            return $result;
        }
    }

    /**
     * Answer Inline.
     *
     * @param array $results
     * @param array $options
     *
     * @return string
     */
    public static function answerInlineQuery($results, $options = [])
    {
        if (!empty($options)) {
            $data = $options;
        }

        if (!isset($options['inline_query_id'])) {
            $get = PHPTelebot::$getUpdates;
            $data['inline_query_id'] = $get['inline_query']['id'];
        }

        $data['results'] = json_encode($results);

        return self::send('answerInlineQuery', $data);
    }

    /**
     * Answer Callback.
     *
     * @param string $text
     * @param array  $options [optional]
     *
     * @return string
     */
    public static function answerCallbackQuery($text, $options = [])
    {
        $options['text'] = $text;

        if (!isset($options['callback_query_id'])) {
            $get = PHPTelebot::$getUpdates;
            $options['callback_query_id'] = $get['callback_query']['id'];
        }

        return self::send('answerCallbackQuery', $options);
    }

    /**
     * Create curl file.
     *
     * @param string $path
     *
     * @return string
     */
    private static function curlFile($path)
    {
        // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
        // See: https://wiki.php.net/rfc/curl-file-upload
        if (function_exists('curl_file_create')) {
            return curl_file_create($path);
        } else {
            // Use the old style if using an older version of PHP
            return "@$path";
        }
    }

    /**
     * Get message properties.
     *
     * @return array
     */
    public static function message()
    {
        $get = PHPTelebot::$getUpdates;
        if (isset($get['message'])) {
            return $get['message'];
        } elseif (isset($get['callback_query'])) {
            return $get['callback_query'];
        } elseif (isset($get['inline_query'])) {
            return $get['inline_query'];
        } elseif (isset($get['edited_message'])) {
            return $get['edited_message'];
        } elseif (isset($get['channel_post'])) {
            return $get['channel_post'];
        } elseif (isset($get['edited_channel_post'])) {
            return $get['edited_channel_post'];
        } else {
            return [];
        }
    }

    /**
     * Mesage type.
     *
     * @return string
     */
    public static function type()
    {
        $getUpdates = PHPTelebot::$getUpdates;

        if (isset($getUpdates['message']['text'])) {
            return 'text';
        } elseif (isset($getUpdates['message']['photo'])) {
            return 'photo';
        } elseif (isset($getUpdates['message']['video'])) {
            return 'video';
        } elseif (isset($getUpdates['message']['audio'])) {
            return 'audio';
        } elseif (isset($getUpdates['message']['voice'])) {
            return 'voice';
        } elseif (isset($getUpdates['message']['document'])) {
            return 'document';
        } elseif (isset($getUpdates['message']['sticker'])) {
            return 'sticker';
        } elseif (isset($getUpdates['message']['venue'])) {
            return 'venue';
        } elseif (isset($getUpdates['message']['location'])) {
            return 'location';
        } elseif (isset($getUpdates['inline_query'])) {
            return 'inline';
        } elseif (isset($getUpdates['callback_query'])) {
            return 'callback';
        } elseif (isset($getUpdates['message']['new_chat_member'])) {
            return 'new_chat_member';
        } elseif (isset($getUpdates['message']['left_chat_member'])) {
            return 'left_chat_member';
        } elseif (isset($getUpdates['message']['new_chat_title'])) {
            return 'new_chat_title';
        } elseif (isset($getUpdates['message']['new_chat_photo'])) {
            return 'new_chat_photo';
        } elseif (isset($getUpdates['message']['delete_chat_photo'])) {
            return 'delete_chat_photo';
        } elseif (isset($getUpdates['message']['group_chat_created'])) {
            return 'group_chat_created';
        } elseif (isset($getUpdates['message']['channel_chat_created'])) {
            return 'channel_chat_created';
        } elseif (isset($getUpdates['message']['supergroup_chat_created'])) {
            return 'supergroup_chat_created';
        } elseif (isset($getUpdates['message']['migrate_to_chat_id'])) {
            return 'migrate_to_chat_id';
        } elseif (isset($getUpdates['message']['migrate_from_chat_id '])) {
            return 'migrate_from_chat_id ';
        } elseif (isset($getUpdates['edited_message'])) {
            return 'edited';
        } elseif (isset($getUpdates['message']['game'])) {
            return 'game';
        } elseif (isset($getUpdates['channel_post'])) {
            return 'channel';
        } elseif (isset($getUpdates['edited_channel_post'])) {
            return 'edited_channel';
        } else {
            return 'unknown';
        }
    }

    /**
     * Create an action.
     *
     * @param string $name
     * @param array  $args
     *
     * @return array
     */
    public static function __callStatic($action, $args)
    {
        $param = [];
        $firstParam = [
            'sendMessage' => 'text',
            'sendPhoto' => 'photo',
            'sendVideo' => 'video',
            'sendAudio' => 'audio',
            'sendVoice' => 'voice',
            'sendDocument' => 'document',
            'sendSticker' => 'sticker',
            'sendVenue' => 'venue',
            'sendChatAction' => 'action',
            'setWebhook' => 'url',
            'getUserProfilePhotos' => 'user_id',
            'getFile' => 'file_id',
            'getChat' => 'chat_id',
            'leaveChat' => 'chat_id',
            'getChatAdministrators' => 'chat_id',
            'getChatMembersCount' => 'chat_id',
            'sendGame' => 'game_short_name',
            'getGameHighScores' => 'user_id',
        ];

        if (!isset($firstParam[$action])) {
            if (isset($args[0]) && is_array($args[0])) {
                $param = $args[0];
            }
        } else {
            $param[$firstParam[$action]] = $args[0];
            if (isset($args[1]) && is_array($args[1])) {
                $param = array_merge($param, $args[1]);
            }
        }

        return call_user_func_array('self::send', [$action, $param]);
    }
}
