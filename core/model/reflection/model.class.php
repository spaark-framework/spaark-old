<?php namespace Spaark\Core\Model\Reflection;

class Model extends \Spaark\Core\Model\Base\Model
{
    private $reflector;

    protected function __fromModel($model)
    {
        $this->reflector = new \ReflectionClass($model);
    }

    public function hasPublicMethod($method)
    {
        return
            $this->reflector->hasMethod($method) &&
            $this->reflector->getMethod($method)->isPublic();
    }

    public function getterMethod($method)
    {
        try
        {
            return GetterMethod::fromCallback(array
            (
                $this,
                $this->reflector->getMethod($method)
            ));
        }
        catch (\Exception $e)
        {
            return NULL;
        }
    }
}