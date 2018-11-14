<?php
/**
 * Базовый виджет
 * @author Alex Gorbylev <alex@gorbylev.ru>
 * @date 2012.03.11
 */
abstract class BaseWidget implements IWidget
{
	protected $name					= null;
	protected $templateName			= null;
	protected $list					= null;

	/** @var ViewResolver */
	protected static $viewResolver;
	/** @var PartViewer */
	protected $viewer = null;

	public function __construct($name = null)
	{
		$this->name = $name;
	}

	protected function getViewer() {
		if (!$this->viewer) {
			assert(self::$viewResolver instanceof ViewResolver, 'set view resolver first');
			$this->viewer = new PartViewer(self::$viewResolver, Model::create());
		}
		return $this->viewer;
	}

	public static function setViewResolver(ViewResolver $viewResolver) {
	    self::$viewResolver = $viewResolver;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return BaseWidget
	 */
	public function set($key, $value)
	{
		if(  ( mb_substr($key, 0, 1) != '_' ) )
			$key = '_'.$key;

		$this->list[$key] = $value;


		return $this;
	}

	/**
	 * @return BaseWidget
	 */
	public function setTemplateName($value)
	{
		$this->templateName = $value;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTemplateName()
	{
		return $this->templateName;
	}

	/**
	 * @return Model
	 */
	protected function makeModel()
	{
		$model = $this->getViewer()->getModel();
		$model->set('_name', $this->name);

		if(
			is_array( $this->list )	&&
			count( $this->list )
		){
			foreach ( $this->list as $key => $value ) {
				$model->set($key, $value);
			}
		}

		return $model;
	}

	/** (non-PHPdoc)
	 * @see core/Base/Stringable::toString()
	 * @throws Exception
	 * @return string
	 */
	public function toString()
	{
		try {
			$this->makeModel();

			ob_start();

			$this->getViewer()->view($this->templateName);

			$source = ob_get_contents();

			ob_end_clean();
		}
		catch (Exception $e) {
			// FIXME
			error_log(__METHOD__ . ': '.$e->__toString() );
			throw $e;
		}

		return (string) $source;
	}

	/** (non-PHPdoc)
	 * @see main/Flow/View::render()
	 * @param Model|null $model
	 * @return void
	 */
	public function render($model = null)
	{
		if( $model )
			$this->getViewer()->
				getModel()->
				merge($model);

		echo $this->toString();

		return /*void*/;
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->toString();
	}
}

