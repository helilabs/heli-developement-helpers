<?php
 
namespace Helilabs\Capital\CURD;

use Illuminate\Support\Collection;
use Helilabs\Capital\Exceptions\JsonException;
use Helilabs\Capital\Repository\RepositoryContract;

abstract Class CurdFactory implements CurdFactoryContract{

	/**
	 * Interface User used to make the request
	 * @var string
	 */
	public $interface = 'web';
	/**
	 * Request User Send through interface
	 * @var Request
	 */
	public $request;

	/**
	 * Model to pase to Creater Find it or create using the "findOrCreateModel" or you can just pass it through setModel
	 * @var Helilabs\Capital\AppBaseModel;
	 */
	public $model;

	/**
	 * Additional Args that controls the request
	 * default are 
	 * [
	 *		'action' => 'new', // determins whether to create a new Model or to find one other options are edit
	 *		'id' => null, // the id of the model you wanna find if action was set to edit
	 *	]
	 *	
	 * @var array
	 */
	public $args;

	/**
	 * Success Message
	 * @var string
	 */
	public $message = 'success';

	/**
	 * Source Repository
	 * @var Helilabs\Capital\Repository\RepositoryContract;
	 */
	public $sourceRepository;

	/**
	 * MainCreater/Editor of the model
	 * @var Helilabs\Capital\CURD\CurdCreatorContract
	 */
	public $curdCreator;

	/**
	 * Bind one of the repositories to interface
	 * @param Helilabs\Capital\Repository\RepositoryContract $sourceRepository SourceRepository to fetch model through
	 * @param Helilabs\Capital\CURD\CurdCreatorContract $curdCreator MainCreater/Editor of the model
	 */
	public function __construct( RepositoryContract $sourceRepository, CurdCreatorContract $curdCreator){
		$this->sourceRepository = $sourceRepository;
		$this->curdCreator = $curdCreator;
	}

	public function setInterface($interface){
		$this->interface = $interface;
		return $this;
	}

	public function setSuccessMessage( $message ){
		$this->message = $message;
		return $this;
	}

	/**
	 * set Request Data to be parsed
	 * @param Illuminate\Support\Collection $request [description]
	 */
	public function setRequest( Collection $request ){
		$this->request = $request;
		return $this;
	}

	public function setArgs( $args = array() ){
		$this->args = $args;
		return $this;
	}

	/**
	 * use this instead of findOrCreateModel to pass model from outsite Curd
	 * @param HeliLabs\Capital\AppBaseModel $model [description]
	 */
	public function setModel( $model ){
		$this->model = $model;
		return $this;
	}

	/**
	 * Creates the Model or 
	 * @return $this Helilabs\Capital\CurdFactory
	 */
	public function findOrCreateModel(){
		if( $this->args['action'] == 'new' ){
			$this->createModel();
			return $this;
		}

		$this->findModel();
		return $this;
	}

	public function findModel(){
		if( !isset( $this->args['id'] )){
			$message = 'id not provided';

			if( $this->interface == 'api' ){
				throw new JsonException( ['error' => $message ] );
			}

			throw new \Exception( $message );
		}

		$this->model = $this->sourceRepository->where('id' , $this->args['id'] )->first();
		
		if( !$this->model ){

			$message = 'Model Not Found' ;

			if( $this->interface == 'api' ){
				throw new JsonException( ['error' => $message ] );
			}

			throw new \Exeption( $message );
		}

		return $this;
	}

	public function doTheJob(){
		try{

			$this->findOrCreateModel();
			$this->curdCreator
			 	->setRequest( $this->request )
			 	->setArgs( $this->args )
			 	->setInterface( $this->interface )
			 	->setModel( $this->model )
			 	->doAction();

			if($this->interface == 'api'){
				return response()->json([
					'code' => 1,
					'message' => $this->message
				]);
			}

			$this->afterSuccessCallback();
			return $this->redirectOnSuccess();

		}catch( JsonException $e ){
			//Json Exception are thrown in api interface
			return response()->json([
				'code' => 0,
				'message' => $e->getDecodedMessage()
			]);
			
		}catch( \Exception $e ){
			$this->afterFailureCallback( $e->getMessage() );
			return redirect()->back()->withInput()->withErrors( $this->curdCreator->getModel()->getValidator() );
		}
	}

	/**
	 * Create New Model from the Model
	 * @return Helilabs\Capital\CURD\CurdFactory $this
	 */
	public abstract function createModel();

	/**
	 * Where to redirect after successfull creation
	 * @return string url to redirect to after success
	 */
	public abstract function redirectOnSuccess();

	/**
	 * Function to be excuted after operation success on web interface
	 * @return Helilabs\Capital\CURD\CurdFactory $this
	 */
	public abstract function afterSuccessCallback();


	/**
	 * Function to be excuted after operation failure on web interface
	 * @return Helilabs\Capital\CURD\CurdFactory $this
	 */
	public abstract function afterFailureCallback( $errorMessage );


}