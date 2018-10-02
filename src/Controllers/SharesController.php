<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SharesController {
  protected $container;
  protected $sharesValidation;

  public function __construct (Slim\Container $container) {
    // $this->container = $container;
    $this->sharesValidation = $container->get('sharesValidation');
  }

  /**
  * Get a listing of the resource.
  *
  * @param Slim\Http\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function index (Request $request, Response $response, array $args) {
    $shares = Share::all();
    foreach($shares as $share) {
      $member_info = $share->member()->first();
      $share['name'] = $member_info->name;
      $share['id_no'] = $member_info->id_no;
    }
    return $response->withJson(['shares' => $shares]);
  }

  /**
  * Get a single member's share history
  *
  * @param Slim\Htt\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function getMemberShares(Request $request, Response $response, array $args) {
    try {
      $member = Member::findOrFail($args['id']);

      return $response->withJson(['shares' => $member->shares()->get()]);
    }
    catch (ModelNotFoundException $e) {
      return $response->withJson('Member not found', 404);
    }
  }

  /**
  * Get a single share history
  *
  * @param Slim\Htt\Request $request
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function getShare(Request $request, Response $response, array $args) {
    try {
      $share = Share::findOrFail($args['id']);

      return $response->withJson($share);
    } catch (ModelNotFoundException $e) {
      return $response->withJson('Share not found', 404);
    }
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

      // Validation
      if($this->sharesValidation->hasErrors()) {
        return $response->withJson($this->sharesValidation->getErrors(), 403);
      } else {
        try {
          $payee = Member::findOrFail($input['member_id']);
          $input['paid_by_id'] = $input['member_id'];
          $input['paid_by'] = $payee->name;
        } catch(ModelNotFoundException $e) {
          return $response
            ->withJson(
              "The Member You're trying to add shares to cannot be found",
              403
            );
        }

        $shares = Share::create($input);
        return $response->withJson($shares, 200);
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
    // Heads up incase of invalid JSON
    $input = $request->getParsedBody(); 
      if($input === null) { 
        return $response->withJson( 
          ['error_decoding_json' => 'It seems the JSON provided is invalid'], 
          400, 
          JSON_PRETTY_PRINT 
        ); 
      }
        
      //Check Validation
      if($this->sharesValidation->hasErrors()) {
        return $response->withJson($this->sharesValidation->getErrors(), 403);
      }
      else {
        try {
          $shares = Share::findOrFail($args['id']);

          try {
            $payee = Member::findOrFail($input['member_id']);
            $input['paid_by_id'] = $input['member_id'];
            $input['paid_by'] = $payee->name;
          } catch(ModelNotFoundException $e) {
            return $response
              ->withJson(
                "The Member You're trying to edit their shares to cannot be found",
                403
              );
          }

          $shares->update($input);
          $shares->save();

          return $response->withJson($shares, 200);
        }
        catch (ModelNotFoundException $e) {
          return $response->withJson('Share history not found', 404);
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
}
