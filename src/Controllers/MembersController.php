<?php
use Slim\Http\Request;
use Slim\Http\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Respect\Validation\Validator as v;

class MembersController {
  protected $container;
  protected $memberValidation;

  //public function __construct (\DavidePastore\Slim\Validation\Validation $validation) {
  public function __construct (Slim\Container $container) {
    $this->memberValidation = $container->get('memberValidation');
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
    return $response->withJson(['members' => Member::all()->toArray()]);
  }

  /**
  * Get a specific member
  *
  * @param Slim\Http\Response $response
  * @param Slim\Http\Response $response
  * @param array $args
  *
  * @return Slim\Http\Response
  */
  public function getMember(Request $request, Response $response, array $args) { 
      try {
        $member = Member::findOrFail($args['id']);

        // For the times when only essential member info is needed
        // Without Share and Loan Information
        if (array_key_exists('_essential', $request->getQueryParams())){
          return $response->withJson(['member' => $member]);
        }

        $total_in_shares=0;
        $member_shares = $member->shares()->get();
        if($member_shares!=null)
            foreach($member_shares as $eachshare)
                $total_in_shares+=$eachshare->amount;

        $total_in_loans=0;
        $member_loans = $member->loans()->get();
        if($member_loans!=null)
            foreach($member_loans as $eachloan)
                $total_in_loans+=$eachloan->amount;

        $total_in_guarantees=0;
        $guaranteed = $member->guarants()->get();
        if($guaranteed!=null)
            foreach ($guaranteed as $eachguarantee)
                $total_in_guarantees+=$eachguarantee->amount;

        return $response->withJson(['member'=>$member, 'shares'=>$total_in_shares, 'loans'=>$total_in_loans, 'guaranteed'=>$total_in_guarantees]);
      }
      catch (ModelNotFoundException $e) {
        return $response->withJson('Member not found', 404);
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
    $input = $request->getParsedBody();
    if($input === null) {
      return $response->withJson(
        ['error_decoding_json' => json_last_error_msg()],
        400,
        JSON_PRETTY_PRINT
      );
    }

    var_dump($input); exit;
    //return $request->getParsedBody();
      //return $request->getAttribute('errors');
     if($this->memberValidation->hasErrors()) {
      //if(true) {
        //return "Error";
       return $response->withJson($this->memberValidation->getErrors());
     }
     else {
       return $response->withJson("All is well");
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
