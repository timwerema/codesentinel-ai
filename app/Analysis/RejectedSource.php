<?php

namespace App\Analysis;

use RuntimeException;

/** Thrown when submitted code is refused. The message is safe to show the user. */
class RejectedSource extends RuntimeException {}
