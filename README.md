WorkerPool
==========

**Parallel Processing WorkerPool for PHP**

_This library is in its infancy. I am adding features to it as I require them._

## Examples


The WorkerPool class provides a very simple interface to pass data to a worker pool and have it processed.
You can at any time fetch the results from the workers. Each worker child can return any value that can be [serialized][serialize].

### A simple example

```php
<?php
require_once(__DIR__.'/src/QXSClosureWorker.php');

$wp=new \QXS\WorkerPool\WorkerPool();
$wp->setWorkerPoolSize(4)
   ->create(new \QXS\WorkerPool\ClosureWorker(
                        /**
                          * @param mixed $input the input from the WorkerPool::run() Method
                          * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to synchronize calls accross all workers
                          * @param \ArrayObject $storage a persistent storage for the current child process
                          */
                        function($input, $semaphore, $storage) {
                                echo "[".getmypid()."]"." hi $input\n";
                                sleep(rand(1,3)); // this is the working load!
                                return $input; // return null here, in case you do not want to pass any data to the parent 
                        }
                )
);


for($i=0; $i<10; $i++) {
        $wp->run($i);
        sleep(1);
}

$wp->waitForAllWorkers(); // wait for all workers

foreach($wp as $val) {
        var_dump($val);  // dump the returned values
}

```

### A more sophisticated example

```php
<?php
require_once(__DIR__.'/src/QXSWorkerPool.php');

use QXS\WorkerPool\WorkerPool;
use QXS\WorkerPool\Worker;
use QXS\WorkerPool\Semaphore;


/**
 * Our Worker Class
 */
Class MyWorker implements Worker {
        protected $sem;
        /**
         * after the worker has been forked into another process
         *
         * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to run synchronized tasks
         * @throws \Exception in case of a processing Error an Exception will be thrown
         */
        public function onProcessCreate(Semaphore $semaphore) {
                // semaphore can be used in the run method to synchronize the workers
                $this->sem=$semaphore;
                // write something to the stdout
                echo "\t[".getmypid()."] has been created.\n";
                // initialize mt_rand
                list($usec, $sec) = explode(' ', microtime());
                mt_srand( (float) $sec + ((float) $usec * 100000) );
        }
        /**
         * before the worker process is getting destroyed
         *
         * @throws \Exception in case of a processing Error an Exception will be thrown
         */
        public function onProcessDestroy() {
                // write something to the stdout
                echo "\t[".getmypid()."] will be destroyed.\n";
        }
        /**
         * run the work
         *
         * @param Serializeable $input the data, that the worker should process
         * @return Serializeable Returns the result
         * @throws \Exception in case of a processing Error an Exception will be thrown
         */
        public function run($input) {
                $input=(string)$input;
                echo "\t[".getmypid()."] Hi $input\n";
                sleep(mt_rand(0,10)); // this is the workload!
                // and sometimes exceptions might occur
                if(mt_rand(0,10)==9) {
                        throw new \RuntimeException('We have a problem for '.$input.'.');
                }
                return "Hi $input"; // return null here, in case you do not want to pass any data to the parent
        }
}


$wp=new WorkerPool();
$wp->setWorkerPoolSize(10)
   ->create(new MyWorker());

// produce some tasks
for($i=1; $i<=50; $i++) {
        $wp->run($i);
}

// some statistics
echo "Busy Workers:".$wp->getBusyWorkers()."  Free Workers:".$wp->getFreeWorkers()."\n";

// wait for completion of all tasks
$wp->waitForAllWorkers();

// collect all the results
foreach($wp as $val) {
        if(isset($val['data'])) {
                echo "RESULT: ".$val['data']."\n";
        }
        elseif(isset($val['workerException'])) {
                echo "WORKER EXCEPTION: ".$val['workerException']['class'].": ".$val['workerException']['message']."\n".$val['workerException']['trace']."\n";
        }
        elseif(isset($val['poolException'])) {
                echo "POOL EXCEPTION: ".$val['poolException']['class'].": ".$val['poolException']['message']."\n".$val['poolException']['trace']."\n";
        }
}


// write something, before the parent exits
echo "ByeBye\n";

```

### Transparent output to ps

See what's happening when running a PS:

```
root      2378  0.5  0.4 260256  8856 pts/1    S+   22:28   0:00          \_ simpleExample.php: Parent
root      2379  0.0  0.2 260256  4588 pts/1    S+   22:28   0:00              \_ simpleExample.php: Worker 1 of QXS\WorkerPool\ClosureWorker [busy]
root      2380  0.0  0.2 260256  4616 pts/1    S+   22:28   0:00              \_ simpleExample.php: Worker 2 of QXS\WorkerPool\ClosureWorker [busy]
root      2381  0.0  0.2 260256  4424 pts/1    S+   22:28   0:00              \_ simpleExample.php: Worker 3 of QXS\WorkerPool\ClosureWorker [free]
root      2382  0.0  0.2 260256  4424 pts/1    S+   22:28   0:00              \_ simpleExample.php: Worker 4 of QXS\WorkerPool\ClosureWorker [free]
```

  [serialize]: http://php.net/serialize