<?php
/*
For more information on how PG Git repos are handled, and on how they interact with GitHub, see /data/git/README.md
*/

define(GITHUB_SECRET_FILE_PATH, '/data/git/secrets/gutenbergbooks-github-secret'); //Set in the GitHub gutenbergbooks global webhook settings.
define(WEBHOOK_LOG_FILE_PATH, '/data/git/webhooks-github.log'); //Must be writable by www-data user.
define(GUTENBERG_REPO_ROOT, '/data/htdocs/gutenberg/'); //Include trailing slash!

//Helper functions and classes
function WriteToLog($message){
	$f = fopen(WEBHOOK_LOG_FILE_PATH, 'a+');
	fwrite($f, gmdate('Y-m-d H:i:s') . "\t" . $message . "\n");
	fclose($f);
}

class WebhookException extends \Exception{
	public $Data;

	public function __construct($message = '', $data = ''){
		$this->Data = $data;
		parent::__construct($message);
	}
}

class NoopException extends \Exception{
}

//Start script body
try{
	WriteToLog('Received GitHub webhook');

	if($_SERVER['REQUEST_METHOD'] == 'POST'){
		$post = file_get_contents('php://input');

		//Validate the GitHub secret.
		try{
			$splitHash = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE']);
			$hashAlgorithm = $splitHash[0];
			$hash = $splitHash[1];

			if(!hash_equals($hash, hash_hmac($hashAlgorithm, $post, preg_replace("/[\r\n]/ius", "", file_get_contents(GITHUB_SECRET_FILE_PATH))))){
				throw new WebhookException();
			}
		}
		catch(WebhookException $ex){
			throw new WebhookException('Invalid GitHub webhook secret', $post);
		}

		//Sanity check before we continue.
		if(!array_key_exists('HTTP_X_GITHUB_EVENT', $_SERVER)){
			throw new WebhookException('Couldn\'t understand HTTP request', $post);
		}

		$data = json_decode($post, true);

		//Decide what event we just received.
		switch($_SERVER['HTTP_X_GITHUB_EVENT']){
			case 'ping':
				//Silence on success
				WriteToLog('Event type: ping');
				throw new NoopException();
				break;
			case 'push':
				WriteToLog('Event type: push');

				//Get the ebook ID.  Our repo names are simply the Gutenberg ebook ID number.
				//PHP doesn't throw WebhookExceptions on invalid array indexes, so check that first.
				if(!array_key_exists('repository', $data) || !array_key_exists('name', $data['repository'])){
					throw new WebhookException('Couldn\'t understand HTTP POST data', $post);
				}

				$ebookId = $data['repository']['name'];

				//Sanity check on ebook ID.  Ebook IDs must be numeric.
				if(!ctype_digit($ebookId)){
					throw new WebhookException('Couldn\'t understand ebook ID: ' . $ebookId, $post);
				}

				//Get the filesystem path for the ebook ID.
				$dir = GUTENBERG_REPO_ROOT;

				$digits = str_split($ebookId);

				array_pop($digits);

				if(sizeof($digits) == 0){
					$dir = $dir . '0/' . $ebookId . '/';
				}
				else{
					foreach($digits as $digit){
						$dir .= $digit . '/';
					}

					$dir = $dir . $ebookId . '/';
				}

				WriteToLog('Processing ebook #' . $ebookId . ' located at ' . $dir);

				//Check the local repo's last commit.  If it matches this push, then don't do anything; we're already up to date.
				$retval = '';
				if(exec('git -C ' . escapeshellarg($dir) . ' rev-parse HEAD 2>&1; echo $?', $retval) != 0){
					WriteToLog('Error getting last local commit.  Output: ' . implode("\n", $retval));
					throw new WebhookException('Couldn\'t process ebook #' . $ebookId, $post);
				}
				else{
					if($data['after'] == $retval[0]){
						//This commit is already in our local repo, so silent success
						WriteToLog('Local repo already in sync, no action taken');
						throw new NoopException();
					}
				}

				//Now that we have the ebook filesystem path, pull the latest commit from GitHub.
				//We use `sudo` to become the `github` Unix user, so that we have write permissions to the target, and so that we don't mess up ownership/group permissions on any new/modified files.
				if(exec('sudo -u gutenbergbooks-github-bot git -C ' . escapeshellarg($dir) . ' pull github 2>&1; echo $?', $retval) != 0){
					WriteToLog('Error pulling from GitHub.  Output: ' . implode("\n", $retval));
					throw new WebhookException('Couldn\'t process ebook #' . $ebookId, $post);
				}
				else{
					WriteToLog('git pull from GitHub complete');
				}
				break;
			default:
				throw new WebhookException('Unrecognized GitHub webhook event', $post);
				break;
		}
	}
	else{
		throw new WebhookException('Expected HTTP POST');
	}

	http_response_code(204); //Success, no content
}
catch(WebhookException $ex){
	//Uh oh, something went wrong!
	//Log detailed error and debugging information locally
	WriteToLog('Webhook failed!  Error: ' . $ex->getMessage());
	WriteToLog('Webhook POST data: ' . $ex->Data);

	//Print less details to the client
	print($ex->getMessage());

	//"Client error"
	http_response_code(400);
}
catch(NoopException $ex){
	//We arrive here because a special case required us to take no action for the request, but execution also had to be interrupted.
	//For example, we received a request for a known repo for which we must ignore requests.

	//"Success, no content"
	http_response_code(204);
}
finally{
	WriteToLog('--------------');
}

?>
