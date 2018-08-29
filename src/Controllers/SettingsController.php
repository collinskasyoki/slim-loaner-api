<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SettingsController {
  protected $container;
  protected $settingsValidator;

  public function __construct (Slim\Container $container) {
    // $this->container = $container;
    $this->settingsValidator = $container->settingsValidator;
  }

  /**
  * Get the first settings column
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function index (Request $request, Response $response, array $args) {
    if (array_key_exists('_all', $request->getQueryParams())){
      return $response->withJson(['settings' => Setting::all()]);
    }

    return $response->withJson(['settings' => Setting::all()->first()]);
  }

  /**
  * Store the specified resource in storage
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function store (Request $request, Response $response, array $args) {
     // Heads up incase of an invaild JSON submitted
     $input = $request->getParsedBody(); 
       if($input === null) { 
         return $response->withJson( 
           ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
           400, 
           JSON_PRETTY_PRINT 
         ); 
       }

       if($this->settingsValidator->hasErrors()) {
         return $response->withJson($this->settingsValidator->getErrors(), 403);
       } else {
         if(strlen($input['notification_number'])==9)
             $input['notification_number'] = '+254'.substr($input['notification_number'], 0);

         $settings = Setting::create($input);

         return $response->withJson($settings);
       }
  }

  /**
  * Update specified resource in storage
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function update (Request $request, Response $response, array $args) {
    // Heads up incase of an invaild JSON submitted
    $input = $request->getParsedBody(); 
      if($input === null) { 
        return $response->withJson( 
          ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
          400, 
          JSON_PRETTY_PRINT 
        ); 
      }

      if($this->settingsValidator->hasErrors()) {
        return $response->withJson($this->settingsValidator->getErrors(), 403);
      } else {
        try {
          $setting = Setting::findOrFail($args['id']);

          // Kenyan phone numbers are circulated without the country code
          // and prepended with a zero
          // Append the country code +254 to the phone number
          // The preceding zero should already be scrapped off
          if(strlen($input['notification_number'])==9)
              $input['notification_number'] = '+254'.substr($input['notification_number'], 0);

          $setting->update($input);
          $setting->save();

          return $response->withJson($setting);
        }
        catch (ModelNotFoundException $e) {
          return $response->withJson('Settings not found', 404);
        }
      }
  }

  /**
  * Remove specified resource from storage.
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function destroy (Request $request, Response $response, array $args) {

  }

  private function checkNullJSON(Request $request, Response $response) {
    // Heads up incase of an invaild JSON submitted
    $input = $request->getParsedBody(); 
      if($input === null) { 
        return $response->withJson( 
          ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
          400, 
          JSON_PRETTY_PRINT 
        ); 
      }

      return true;
  }
}
