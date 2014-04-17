<?php
/**
 * A simple Object wrapper arround the semaphore functions
 */

namespace QXS\WorkerPool;

/**
 * Semaphore Class
 * 
 * <code>
 * $t=new Semaphore();
 * $t->create(Semaphore::SEM_FTOK_KEY);
 * // acquire &&  release
 * $t->acquire();
 * echo "We are in the sem\n";
 * $t->release();
 * // acquire && release (aliases)
 * $t->synchronizedBegin();
 * echo "We are in the sem\n";
 * $t->synchronizedEnd();
 * 
 * $t->destroy();
 * </code>
 */
class Semaphore {
	/** generate a random key */
	const SEM_RAND_KEY='rand';
	/** generate a key based on ftok */
	const SEM_FTOK_KEY='ftok';
	/** @var resource the semaphore resource */
	protected $semaphore=null;
	/** @var int the key that is used to access the semaphore */
	protected $semKey=null;

	/**
	 * Returns the key, that can be used to access the semaphore
	 * @return int the key of the semaphore
	 */
	public function getSemaphoreKey() {
		return $this->semKey;
	}
	
	/**
	 * Create a semaphore
	 * @param int $semKey the key of the semaphore - use a specific number or Semaphore::SEM_RAND_KEY or Semaphore::SEM_FTOK_KEY
	 * @param $maxAcquire the maximum number of processes, that can acquire the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 */
	public function create($semKey=Semaphore::SEM_FTOK_KEY, $maxAcquire=1) {
		if(is_resource($this->semaphore)) {
			throw new SemaphoreException('Semaphore has already been created.');
		}
		
		if(!is_int($maxAcquire)) {
			$maxAcquire=1;
		}

		// randomly generate semaphore, without collision
		if($semKey==Semaphore::SEM_RAND_KEY) {
			$retries=5;
		}
		else {
			$retries=1;
		}
		// try to generate a semaphore
		while(!is_resource($this->semaphore) && $retries>0) {
			$retries--;
			// generate a semKey
			if(!is_int($semKey)) {
				if($semKey==Semaphore::SEM_RAND_KEY) {
					$this->semKey=mt_rand(1 , PHP_INT_MAX);
				}
				else {
					$this->semKey=ftok(__FILE__, 's');
				}
			}
			else {
				$this->semKey=$semKey;
			}
			$this->semaphore=sem_get($this->semKey, $maxAcquire, 0666, 0);
		}
		if(!is_resource($this->semaphore)) {
			$this->semaphore=null;
			$this->semKey=null;
			throw new SemaphoreException('Cannot create the semaphore.');
		}

		return $this;
	}

	/**
	 * Acquire the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 */
	public function acquire() {
		if(!@sem_acquire($this->semaphore)) {
			throw new SemaphoreException('Cannot acquire the semaphore.');
		}
		return $this;
	}

	/**
	 * Releases the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 */
	public function release() {
		if(!@sem_release($this->semaphore)) {
			throw new SemaphoreException('Cannot release the semaphore.');
		}
		return $this;
	}

	/**
	 * Acquire the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 * @see \QXS\WorkerPool\Semaphore::acquire()
	 */
	public function synchronizedBegin() { return $this->acquire(); }
	/**
	 * Releases the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 * @see \QXS\WorkerPool\Semaphore::release()
	 */
	public function synchronizedEnd() { return $this->release(); }

	/**
	 * Run something synchronized
	 * @param \Closure $closure the closure, that should be run synchronized
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 */
	public function synchronize(\Closure $closure) { 
		$this->acquire();
		$closure->__invoke();
		$this->release();
		return $this;
	}

	/**
	 * Destroys the semaphore
	 * @throws \QXS\WorkerPool\SemaphoreException in case of an error
	 * @return \QXS\WorkerPool\Semaphore the current object
	 */
	public function destroy() {
		if(!is_resource($this->semaphore)) {
			throw new SemaphoreException('Semaphore hasn\'t yet been created.');
		}
		if(!sem_remove($this->semaphore)) {
			throw new SemaphoreException('Cannot remove the semaphore.');
		}

		$this->semaphore=null;
		$this->semKey=null;
		return $this;
	}

}
