<?php
/**
 * This file is part of the EasyCore package.
 *
 * (c) Marcin Stodulski <marcin.stodulski@devsprint.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace mstodulski\database;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node) : ?int
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            return NodeTraverser::REMOVE_NODE;
        } else if ($node instanceof Node\Stmt\Property) {
            return NodeTraverser::REMOVE_NODE;
        } else if ($node instanceof Node\Stmt\Use_) {
            return NodeTraverser::REMOVE_NODE;
        }

        return null;
    }
}