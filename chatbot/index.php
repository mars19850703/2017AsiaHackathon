<?php

$access_token     = "EAAIg1xemrnEBACZB7MSbXWOEZBwpAOJPpfrWSZCBwEH4cPfJQmQE2oVjBMcgxpUlyM1EJ6QadZB6n8rI4ta8h3tiSIkrEFS8CDNFu06ZAL9XJ3fJWk7bDZCtBhUYrB1AmYCbgVSxNHg4nLZClvYJVNkE4f3rM9Gv6IzxH7wC5sjhPVEB25VFBZB1";
$verify_token     = "test_hackathon";
$hub_verify_token = null;
if (isset($_REQUEST['hub_challenge'])) {
    $challenge        = $_REQUEST['hub_challenge'];
    $hub_verify_token = $_REQUEST['hub_verify_token'];
}
if ($hub_verify_token === $verify_token) {
    echo $challenge;
}

if (isset($GLOBALS['msg'])) {
    $GLOBALS['msg']                      = [];
    $GLOBALS['msg'][$sender]['location'] = false;
}

function strposa($haystack, $needles = array(), $offset = 0)
{
    $chr = array();
    foreach ($needles as $needle) {
        $res = mb_strpos($haystack, $needle, $offset);
        if ($res !== false) {
            return true;
        }
    }
}

function isLocation($message)
{
    if (isset($message['attachments']) && $message['attachments'][0]['type'] == 'location') {
        return true;
    } else {
        return false;
    }
}

function isLastMsgLocation($msg)
{
    $location = $msg['entry'][0]['messaging'][0]['message'];
    if (isset($location['attachments']) && $location['attachments'][0]['type'] == 'location') {
        return true;
    } else {
        return false;
    }
}

$dsn = "mysql:host=localhost;dbname=hulala";
$db  = new PDO($dsn, 'root', 'root');

$tmpInput = file_get_contents('php://input');

$filePath = '/tmp/msg.txt';
$handle   = fopen($filePath, 'a');
$result   = fwrite($handle, $tmpInput . ',');
fclose($handle);

$input = json_decode($tmpInput, true);
if (isset($input['entry'][0]['messaging'][0]['message'])) {
    $sender           = $input['entry'][0]['messaging'][0]['sender']['id'];
    $message          = $input['entry'][0]['messaging'][0]['message'];
    $message_to_reply = '1111111';
    $messageType      = 0;

    $sql      = "SELECT * FROM messages WHERE sender = '" . $sender . "' ORDER BY seq DESC LIMIT 0, 1";
    $query    = $db->query($sql);
    $datalist = $query->fetchAll();
    $msg      = null;
    foreach ($datalist as $datainfo) {
        $msg = json_decode(base64_decode($datainfo['message']), true);
    }

    if ($message['seq'] != 0) {
        $sql = "INSERT INTO messages (sender, seq, message) VALUES ('" . $sender . "', " . $message['seq'] . ", '" . base64_encode($tmpInput) . "')";
        $db->exec($sql);
    }

    // if (!isset($message['is_echo'])) {
    //     $ch      = curl_init();
    //     $options = [
    //         CURLOPT_URL            => 'http://35.189.175.157:5001/',
    //         CURLOPT_VERBOSE        => 0,
    //         CURLOPT_RETURNTRANSFER => true,
    //         CURLOPT_POST           => true,
    //         CURLOPT_POSTFIELDS     => json_encode(['lat' => $location['attachments'][0]['payload']['coordinates']['lat'], 'lon' => $location['attachments'][0]['payload']['coordinates']['long']]),
    //     ];
    //     curl_setopt_array($ch, $options);
    //     $result = json_decode(curl_exec($ch), true);
    //     curl_close($ch);
    // }

    /**
     * Some Basic rules to validate incoming messages
     */
    if (isLocation($message)) {
        $messageType = 2;
        $ch          = curl_init();
        $options     = [
            CURLOPT_URL            => 'http://35.189.175.157:5001/',
            CURLOPT_VERBOSE        => 0,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => false,
            // CURLOPT_POSTFIELDS     => http_build_query(['lat' => $location['attachments'][0]['payload']['coordinates']['lat'], 'lon' => $location['attachments'][0]['payload']['coordinates']['long']]),
        ];
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);

        $filePath = '/tmp/msg1.txt';
        $handle   = fopen($filePath, 'a');
        $res      = fwrite($handle, $result);
        fclose($handle);

        $result1 = json_decode($result, true);
        $text    = "紫外線指數" . $result1['aqi_status'] . ",";
        $text .= "空氣" . $result1['aqi_status'] . ",";
        $text .= "空氣建議 : " . $result1['aqi_suggest'] . ",";
        $text .= "氣溫" . $result1['feeling'] . ",";
        $text .= "天氣狀況" . $result1['status'];
        curl_close($ch);
    } else {
        if (strposa($message['text'], ['帽子', '防曬油', '口罩'])) {
            if (mb_strpos($message['text'], '帽子') !== false) {
                $imageUrl = 'https://images.dappei.com/uploads/tag/image/20728/large_4e471321b5853bb9.jpg';
                $website  = 'http://mall.pchome.com.tw/prod/QCAD43-A9005TNBL';
            } else if (mb_strpos($message['text'], '防曬') !== false) {
                $imageUrl = 'http://5.blog.xuite.net/5/3/c/2/11162127/blog_353352/txt/17392101/1.jpg';
                $website  = 'http://24h.pchome.com.tw/prod/DDAOJG-A900858Y8';
            } else {
                $imageUrl = 'http://www.gzo.com.tw/images/product/6/4.png';
                $website  = 'http://24h.pchome.com.tw/prod/DABC0V-A9007FDSG';
            }
            $messageType = 3;
        } else {
            if ($msg == null) {
                $messageType = 1;
            } else {
                if (isLastMsgLocation($msg)) {
                    // if (strposa($message['text'], ['買帽子', '買防曬油', '買口罩'])) {
                    //     $messageType = 3;
                    // } else {
                    $messageType = 2;
                    // }
                } else {
                    $messageType = 1;
                }
            }
        }
    }

    //API Url
    $url = 'https://graph.facebook.com/v2.6/me/messages?access_token=' . $access_token;

    //Initiate cURL.
    $ch = curl_init($url);

    switch ($messageType) {
        case 1:
            //The JSON data.
            $jsonData = '{
                "recipient":{
                    "id":"' . $sender . '"
                },
                "message":{
                    "text":"Please share your location:' . $test . '",
                    "quick_replies":[
                      {
                        "content_type":"location",
                      }
                    ]
                }
            }';
            break;
        case 2:
            //The JSON data.
            $jsonData = '{
                "recipient":{
                    "id":"' . $sender . '"
                },
                "message":{
                    "text":"' . $text . '",
                    "quick_replies":[
                        {
                            "content_type":"text",
                            "title":"買帽子",
                            "payload":"DEVELOPER_DEFINED_PAYLOAD_FOR_PICKING_GREEN"
                        },
                        {
                            "content_type":"text",
                            "title":"買防曬油",
                            "payload":"DEVELOPER_DEFINED_PAYLOAD_FOR_PICKING_GREEN"
                        },
                        {
                            "content_type":"text",
                            "title":"買口罩",
                            "payload":"DEVELOPER_DEFINED_PAYLOAD_FOR_PICKING_GREEN"
                        }
                    ]
                }
            }';
            break;
        case 3:
            //The JSON data.
            $jsonData = [
                '{
                    "recipient":{
                        "id":"' . $sender . '"
                    },
                    "message":{
                        "attachment":{
                            "type":"image",
                            "payload":{
                                "url":"' . $imageUrl . '"
                            }
                        }
                    }
                }',
                '{
                    "recipient":{
                        "id":"' . $sender . '"
                    },
                    "message":{
                        "attachment":{
                          "type":"template",
                          "payload":{
                            "template_type":"button",
                            "text":"Go to Website",
                            "buttons":[
                              {
                                "type":"web_url",
                                "url":"' . $website . '",
                                "title":"Want to Buy"
                              }
                            ]
                          }
                        }
                    }
                }',
            ];
            break;
        default:
            //The JSON data.
            $jsonData = '{
                "recipient":{
                    "id":"' . $sender . '"
                },
                "message":{
                    "text":"' . "your last mesg:" . $msg['entry'][0]['messaging'][0]['message']['text'] . $message_to_reply . '"
                }
            }';
            break;
    }

    if (is_array($jsonData)) {
        foreach ($jsonData as $j) {
            //Encode the array into JSON.
            $jsonDataEncoded = $j;
            //Tell cURL that we want to send a POST request.
            curl_setopt($ch, CURLOPT_POST, 1);
            //Attach our encoded JSON string to the POST fields.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            //Set the content type to application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            //Execute the request
            if (!empty($input['entry'][0]['messaging'][0]['message'])) {
                $result = curl_exec($ch);
            }
        }
    } else {
        //Encode the array into JSON.
        $jsonDataEncoded = $jsonData;
        //Tell cURL that we want to send a POST request.
        curl_setopt($ch, CURLOPT_POST, 1);
        //Attach our encoded JSON string to the POST fields.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
        //Set the content type to application/json
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        //curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        //Execute the request
        if (!empty($input['entry'][0]['messaging'][0]['message'])) {
            $result = curl_exec($ch);
        }
    }
}
