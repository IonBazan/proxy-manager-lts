<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ProxyManager\ProxyGenerator;

use CG\Generator\PhpClass;
use CG\Generator\PhpProperty;
use CG\Proxy\GeneratorInterface;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\Constructor;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\LazyLoadingMethodInterceptor;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\MagicClone;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\MagicGet;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\MagicIsset;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\MagicSet;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpMethod\MagicSleep;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpProperty\InitializerProperty;
use ProxyManager\ProxyGenerator\LazyLoadingValueHolder\PhpProperty\ValueHolderProperty;
use ReflectionClass;
use ReflectionMethod;

/**
 * Generator for proxies implementing {@see \ProxyManager\Proxy\ValueHolderInterface}
 * and {@see \ProxyManager\Proxy\LazyLoadingInterface}
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 * @license MIT
 */
class LazyLoadingValueHolderGenerator implements GeneratorInterface
{
    /**
     * {@inheritDoc}
     */
    public function generate(ReflectionClass $originalClass, PhpClass $generated)
    {
        $generated->setParentClassName($originalClass->getName());

        $generated->setProperty($valueHolder = new ValueHolderProperty());
        $generated->setProperty($initializer = new InitializerProperty());

        $generated->setMethod(new Constructor($originalClass, $initializer));

        $excluded = array(
            '__get'    => true,
            '__set'    => true,
            '__isset'  => true,
            '__clone'  => true,
            '__sleep'  => true,
            '__wakeup' => true,
        );

        $methods = array_filter(
            $originalClass->getMethods(ReflectionMethod::IS_PUBLIC),
            function (ReflectionMethod $method) use ($excluded) {
                return ! (
                    $method->isConstructor()
                    || isset($excluded[strtolower($method->getName())])
                    || $method->isFinal()
                    || $method->isStatic()
                );
            }
        );

        foreach ($methods as $method) {
            $generated->setMethod(LazyLoadingMethodInterceptor::fromReflection($method, $initializer, $valueHolder));
        }

        $generated->setMethod(new MagicGet($originalClass, $initializer, $valueHolder));
        $generated->setMethod(new MagicSet($originalClass, $initializer, $valueHolder));
        $generated->setMethod(new MagicIsset($originalClass, $initializer, $valueHolder));
        $generated->setMethod(new MagicClone($originalClass, $initializer, $valueHolder));
        $generated->setMethod(new MagicSleep($originalClass, $initializer, $valueHolder));
    }
}
