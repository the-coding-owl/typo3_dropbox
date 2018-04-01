<?php

#if (${NAMESPACE})
namespace ${NAMESPACE};
#end

#if (${TESTED_NAME} && ${NAMESPACE} && !${TESTED_NAMESPACE})
use ${TESTED_NAME};
#elseif (${TESTED_NAME} && ${TESTED_NAMESPACE} && ${NAMESPACE} != ${TESTED_NAMESPACE})
use ${TESTED_NAMESPACE}\\${TESTED_NAME};
#end
use PHPUnit\Framework\TestCase;

/**
 * Class ${NAME}
#if (${NAMESPACE}) * @package ${NAMESPACE}
#end
 * @copyright ${Author} <${AuthorEmail}>
 */
class ${NAME} extends TestCase {

}
