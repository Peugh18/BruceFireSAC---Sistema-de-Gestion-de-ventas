<?php

namespace App\Services\Sunat;

use RuntimeException;

/** SUNAT o RENIEC no tienen ese RUC o DNI (o no es un número que exista). */
class DocumentoNoEncontrado extends RuntimeException {}
