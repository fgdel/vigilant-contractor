<?php

use Slim\Http\Request;
use Slim\Http\Response;

require __DIR__ . '/MultichainRpcWrapper.php';

// Inject MultichainWrapper into the container
$container = $app->getContainer();
$container['multichain'] = function() {
    global $rpcConfig;

    $rpcConfig = array(
        'name' => 'pub_chain2',
        'rpchost' => '34.240.171.227',
        'rpcport' => '4366',
        'rpcuser' => 'multichainrpc',
        'rpcpassword' => 'FTmqMx8LNhQuLvogS7yhFcqqYEfR6s2XsuwKwP77fr1p'        
    );
    
    return new MultichainRpcWrapper($rpcConfig);
};

$app->group('/api', function() {

    $this->get('/asset/{tx_id}', function (Request $request, Response $response) {
        /* IS THIS USED? */
        $tx_id = $request->getAttribute('tx_id');

        $vout = 0;
    
        $multichain = $this->get('multichain');
    
        if (no_displayed_error_result($data, multichain('gettxoutdata', $tx_id, $vout))) {
            $file=txout_bin_to_file(pack('H*', $data));
            
            if (is_array($file)) {
    
                if (strlen($file['mimetype']))
                    header('Content-Type: '.$file['mimetype']);
                
                if (strlen($file['filename'])) {
                    // for compatibility with HTTP headers and all browsers
                    $filename=preg_replace('/[^A-Za-z0-9 \\._-]+/', '', $file['filename']);
                    header('Content-Disposition: inline; filename="'.$filename.'"');
                }
                
                echo $file['content'];
            
            } else
                echo 'File not formatted as expected';
        }        
    });

    $this->get('/download/{txid}/[{vout}]', function (Request $request, Response $response, array $args) {

        $txid = $args['txid'];
        $vout = $args['vout'];

        $multichain = $this->get('multichain');
        
        if ($data = $multichain->getCommandResult('gettxoutdata', $txid, (int)$vout)) {

            $file = $multichain->txOutBinToFile( pack('H*', $data ) );
            
            if (is_array($file)) {

                $filename=preg_replace('/[^A-Za-z0-9 \\._-]+/', '', $file['filename']);

                $content = $response->getBody();
                $content->write( $file['content'] );
                
                $newResponse = $response->withHeader('Content-type', $file['mimetype'])
                ->withHeader('Content-Description', 'File Transfer')
                ->withHeader('Content-Disposition', 'inline; filename=' . $filename)
                ->withHeader('Content-Transfer-Encoding', 'binary')
                ->withHeader('Expires', '0')
                ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                ->withHeader('Pragma', 'public');
                return($newResponse);

            } else {
                $args['name'] = 'txOutBinToFile failed!';
            }
        } else {
            $args['name'] = 'gettxoutdata failed!';
        }
        // Render index view
        return $this->renderer->render($response, 'index.phtml', $args);
    });

    $this->get('/getitem/{txid}', function (Request $request, Response $response, array $args) {

        $txid = $args['txid'];

        $multichain = $this->get('multichain');

        if ($item = $multichain->getCommandResult('getstreamitem', 'public', $txid, true)) {

            $vout = $item['vout'];

            if ($data = $multichain->getCommandResult('gettxoutdata', $txid, (int)$vout)) {

                $file = $multichain->txOutBinToFile( pack('H*', $data ) );
                
                if (is_array($file)) {
    
                    $filename=preg_replace('/[^A-Za-z0-9 \\._-]+/', '', $file['filename']);
    
                    $content = $response->getBody();
                    $content->write( $file['content'] );
                    
                    $newResponse = $response->withHeader('Content-type', $file['mimetype'])
                    ->withHeader('Content-Description', 'File Transfer')
                    ->withHeader('Content-Disposition', 'inline; filename=' . $filename)
                    ->withHeader('Content-Transfer-Encoding', 'binary')
                    ->withHeader('Expires', '0')
                    ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                    ->withHeader('Pragma', 'public');
                    return($newResponse);
    
                }

            } else {
                $response->withHeader('Content-Type', 'application/json');
                $response->write(json_encode(array('txid'=>$txid,'vout'=>$vout)));
            }

        }
        return $response;
    });

    $this->get('/latest/published/{count}', function (Request $request, Response $response) {

        $count = $request->getAttribute('count');

        $multichain = $this->get('multichain');

        $data = $multichain->getCommandResult('liststreamitems', 'public');

        $data = array_reverse($data);

        $dataToReturn = array();

        for ($i=0; $i<$count; $i++)
        {
            $d = array();
            $d['signature'] = $data[$i]['key'];
            $d['blocktime'] = date("Y-m-d H:i:s", $data[$i]['blocktime'])." UTC";
            $d['confirmations'] = $data[$i]['confirmations'];
            $dataToReturn[$i] = $d;
        }
                
        return $response->withJson($dataToReturn)->withHeader('Content-Type', 'application/json');

    });

    $this->post('/publish', function (Request $request, Response $response) {

        $multichain = $this->get('multichain');

        $max_upload_size = $multichain->getMaxDataSize() - 512; // take off space for file name and mime type    
        
        $data = $request->getParsedBody();
    
        if(isset($data['signature']))
            $signature = filter_var($data['signature'], FILTER_SANITIZE_STRING);
        else
            $signature = "Unknown";
    
        if(isset($data['name']))
            $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
        else
            $name = "Unknown";
        
        if(isset($data['email']))
            $email = filter_var($data['email'], FILTER_SANITIZE_STRING);
        else
            $email = "Unknown";
    
        if(isset($data['message']))
            $message = filter_var($data['message'], FILTER_SANITIZE_STRING);
        else
            $message = "NA";
            
        $uploadedFiles = $request->getUploadedFiles();
    
        // handle single input with single file upload
        $uploadedFile = $uploadedFiles['d'];
        
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
    
            if (strlen($uploadedFile->getClientFilename())) {
                $upload_size = $uploadedFile->getSize();
                $upload_limit = $multichain->getMaxDataSize();
    
                if ($upload_size > $upload_limit) {
                    echo '<div class="bg-danger" style="padding:1em;">Uploaded file is too large ('.number_format($upload_size).' > '.number_format($max_upload_size).' bytes).</div>';
                    return;
                } else {
                    $dataFile=$multichain->fileToTxOutBin($uploadedFile->getClientFilename(), $uploadedFile->getClientMediaType(), $uploadedFile->getStream());                
                }
    
            }
            
        }
    
        $dataArray = array("signature" => $signature,"name" => $name, "email"=> $email, "message"=>$message, "file"=>bin2hex($dataFile));
        $dataHex = bin2hex( base64_encode( json_encode($dataArray) ) );
    
        $tx_id = $multichain->getCommandResult('publish', 'public', $signature, $dataHex);
        //$tx_id = $multichain->getCommandResult('publish', 'public', $signature, bin2hex($dataFile));

        $dataToReturn = array();
        //$tx_id = $client->setDebug(true)->executeApi('publish', array("public", $signature, $dataHex));
        
        $longUrl = $_SERVER['HTTP_HOST']."/details/".$signature;
        //$shorUrl = shortUrl($longUrl);
    
        $block_info = $multichain->getCommandResult('getwallettransaction', $tx_id);

        $blockhash = "NA";
        $blocktime = "NA";

        $confirmations = $block_info['confirmations'];

        if ($confirmations > 0) {
            $blockhash = $block_info['blockhash'];
            $blocktime = $block_info['blocktime'];
        }
    
        $dataToReturn['long_url'] = "http://".$longUrl;
        //$dataToReturn['short_url'] = $shorUrl;
        //$datatoreturn[''] = $ ;
        $dataToReturn['signature'] = $signature;
        $dataToReturn['transaction_id'] = $tx_id;
        $dataToReturn['confirmations'] = $confirmations;
        $dataToReturn['blockhash'] = $blockhash;
        $dataToReturn['blocktime'] = $blocktime;
        $dataToReturn['name'] = $name;
        $dataToReturn['email'] = $email;
        $dataToReturn['message'] = $message;
        $dataToReturn['timestamp'] = date('g:i A \o\n l jS F Y \(\T\i\m\e\z\o\n\e \U\T\C\)', time());;
        
        return $response->withJson($dataToReturn)->withHeader('Content-Type', 'application/json');

    });
    
    $this->post('/publish/{signature}', function (Request $request, Response $response) {

        $multichain = $this->get('multichain');

        /* IS THIS USED? */

        $max_upload_size=multichain_max_data_size()-512; // take off space for file name and mime type    
        $signature = $request->getAttribute('signature');
        // insert details here
        $client = new MultichainClient("http://.....", 'multichainrpc', '.....', 3);
        $response->getBody()->write("Hello, $signature");
        $data = $request->getParsedBody();
        if(isset($data['name']))
            $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
        else
            $name = "Unknown";
        
        if(isset($data['email']))
            $email = filter_var($data['email'], FILTER_SANITIZE_STRING);
        else
            $email = "Unknown";
    
        if(isset($data['message']))
            $message = filter_var($data['message'], FILTER_SANITIZE_STRING);
        else
            $message = "NA";
            
        $uploadedFiles = $request->getUploadedFiles();
    
        $this->logger->addInfo("Something interesting is about to happen!");
    
        // handle single input with single file upload
        $uploadedFile = $uploadedFiles['d'];
    
        var_dump($uploadedFiles);
        
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
    
            if (strlen($uploadedFile)) {
    
                if ($uploadedFile->getSize() > $max_upload_size) {
                    echo '<div class="bg-danger" style="padding:1em;">Uploaded file is too large ('.number_format($upload_size).' > '.number_format($max_upload_size).' bytes).</div>';
                    return;
                } else {
                    $dataFile=file_to_txout_bin($uploadedFile->getClientFilename(), $uploadedFile->getClientMediaType(), $uploadedFile->getStream());                
                }
    
            }
            
        }
        
        $dataArray = array("signature" => $signature,"name" => $name, "email"=> $email, "message"=>$message, "file"=>$dataFile);
        $dataJSON = json_encode($dataArray);
        $dataBase64 = base64_encode($dataJSON );
        $dataHex = bin2hex($dataBase64);
    
        //$info = $client->setDebug(true)->getInfo();
        $dataToReturn = array();
        $tx_id = $client->setDebug(true)->executeApi('publish', array("public", $signature, $dataHex));
        
        $longUrl = $_SERVER['HTTP_HOST']."/details/".$signature;
        //$shorUrl = shortUrl($longUrl);
    
        $block_info = $client->setDebug(true)->executeApi('getwallettransaction', array($tx_id));
        $confirmations = $block_info['confirmations'];
        if($confirmations == 0){
            $blockhash = "NA";
            $blocktime = "NA";
        }
        else{
            $blockhash = $block_info['blockhash'];
            $blocktime = $block_info['blocktime'];
        }
    
        $dataToReturn['long_url'] = "http://".$longUrl;
        //$dataToReturn['short_url'] = $shorUrl;
        $dataToReturn['signature'] = $signature;
        $dataToReturn['transaction_id'] = $tx_id;
        $dataToReturn['confirmations'] = $confirmations;
        $dataToReturn['blockhash'] = $blockhash;
        $dataToReturn['blocktime'] = $blocktime;
        $dataToReturn['name'] = $name;
        $dataToReturn['email'] = $email;
        $dataToReturn['message'] = $message;
        $dataToReturn['timestamp'] = date('g:i A \o\n l jS F Y \(\T\i\m\e\z\o\n\e \U\T\C\)', time());;
        
        return $response->withJson($dataToReturn)->withHeader('Content-Type', 'application/json');
        //return $response;
    });

    /* 
     * Get QR code.
     */
    $this->get('/qr/{url}', function (Request $request, Response $response) {
        //    use Endroid\QrCode\ErrorCorrectionLevel;
        //    use Endroid\QrCode\LabelAlignment;
        //    use Endroid\QrCode\QrCode;
           
        $data = $request->getParsedBody();
        if(isset($data['asset'])) {
            $asset = filter_var($data['asset'], FILTER_SANITIZE_STRING);        
        } else {
            $asset = "Unknown";        
        }
    
        $qrCode = new QrCode( $asset );
        $qrCode->setSize(300);
            
        // Set advanced options
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(10);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255]);
        $qrCode->setLabel('Scan the code', 16, __DIR__.'/../assets/fonts/noto_sans.otf', LabelAlignment::CENTER);
        $qrCode->setLogoPath(__DIR__.'/../assets/images/symfony.png');
        $qrCode->setLogoWidth(150);
        $qrCode->setValidateResult(false);
    
        // Directly output the QR code
        //header('Content-Type: '.$qrCode->getContentType());
        //echo $qrCode->writeString();
    
        // Save it to a file
        //$qrCode->writeFile(__DIR__.'/qrcode.png');
    
        // Create a response object
        $response = new Response($qrCode->writeString(), Response::HTTP_OK, ['Content-Type' => $qrCode->getContentType()]);
    });
    
    $this->get('/transaction/{signature}', function (Request $request, Response $response) {
        $signature = $request->getAttribute('signature');

        $multichain = $this->get('multichain');

        $data = array_reverse( $multichain->getCommandResult('liststreamkeyitems', 'public', $signature, true) );

        $txData = $multichain->getCommandResult('getwallettransaction', $data[0]['txid'], false, true);

        return $response->withJson($txData)->withHeader('Content-Type', 'application/json');
    });

    $this->get('/verify/{signature}', function (Request $request, Response $response) {
        $signature = $request->getAttribute('signature');

        $multichain = $this->get('multichain');

        $data = array_reverse( $multichain->getCommandResult('liststreamkeyitems', 'public', $signature, true) );

        $dataToReturn = array();

        foreach($data as $key => $value){
            $d = array();
            $d['signature'] = $signature;
            $d['transaction_id'] = $value['txid'];
            $d['confirmations'] = $value['confirmations'];
            $d['blocktime'] = date('g:i A \o\n l jS F Y \(\T\i\m\e\z\o\n\e \U\T\C\)', $value['blocktime']);
            

            // $value['data'] needs to be a string to decode
            if (is_string($value['data'])) {
                $meta_data = json_decode(base64_decode(hex2bin($value['data'])));
                if (!empty($meta_data->name))
                    $d['name'] = $meta_data->name;
                if (!empty($meta_data->email))
                    $d['email'] = $meta_data->email;
                if (!empty($meta_data->message))
                    $d['message'] = $meta_data->message;
            }

            $d['recorded_timestamp_UTC'] = $value['blocktime'];
            $d['readable_time_UTC'] = date('g:i A \o\n l jS F Y \(\T\i\m\e\z\o\n\e \U\T\C\)', $value['blocktime']);
            $dataToReturn[$key] = $d;
        }
        
        return $response->withJson($dataToReturn)->withHeader('Content-Type', 'application/json');
    });
        
});

/* /About : about.html */
$app->get('/about', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'about.phtml', $args);
});

/* /Contact : contact.html */
$app->get('/contact', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'contact.phtml', $args);
});

/* /Details : details.html */
$app->get('/details/{signature}', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'details.phtml', $args);
});

/* /Verify : verify.html */
$app->get('/verify[/{signature}]', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'verify.phtml', $args);
});

/* Homepage : publish.html */
$app->get('/', function (Request $request, Response $response, array $args) {
    return $this->renderer->render($response, 'publish.phtml', $args);
});
