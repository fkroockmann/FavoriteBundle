<?php

/*
 * Copyright (c) 2011-2015 Lp digital system
 *
 * This file is part of FavoriteBundle.
 *
 * FavoriteBundle is free bundle: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * FavoriteBundle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FavoriteBundle. If not, see <http://www.gnu.org/licenses/>.
 */

namespace LpDigital\Bundle\FavoriteBundle\Listener;

use BackBee\Event\Event;

/**
 * @author Mickaël Andrieu <mickael.andrieu@lp-digital.fr>
 * @author Marian Hodis <marian.hodis@lp-digital.fr>
 */
class ClassContentControllerListener
{
    const CATEGORY_FAVORITE = 'Favoris';

    public static function onPostCategoryCall(Event $event)
    {
        $response = $event->getResponse();

        $entityManager = $event->getApplication()->getEntityManager();
        $user = $event->getApplication()->getBBUserToken()->getUser();

        $favoriteCategory = self::getFavoriteCategory($entityManager, $user->getId());
        if(empty($favoriteCategory['contents'])) {
            $favoriteCategory = self::getDefaultFavoriteCategory($entityManager, $event->getApplication()->getSite()->getUid(), $favoriteCategory);
        }

        $categories = json_decode($response->getContent(), true);

        /* the new category has been pushed on top of class content categories */
        array_unshift($categories, $favoriteCategory);

        $response->setContent(json_encode($categories, true));
    }

    protected static function getFavoriteCategory($entityManager, $id)
    {
        $category = [
            'id' => strtolower(self::CATEGORY_FAVORITE),
            'name' => self::CATEGORY_FAVORITE,
        ];
        
        $userBookMarks = $entityManager
            ->getRepository('LpDigital\Bundle\FavoriteBundle\Entity\BookMark')->findOneByUserId($id);

        if (!empty($userBookMarks)) {
            $userBookMarks = $userBookMarks->getBookMarks();
            $category['contents'] = self::processBookMarks($userBookMarks);
        }
        
        return $category;
    }
    
    /**
     * If user has no favorite blocks show him the default favorite site blocks
     * 
     * @param $entityManager
     * @param string $siteUid
     * @param array $category
     * @return array $category
     */
    protected static function getDefaultFavoriteCategory($entityManager, $siteUid, array $category)
    {
        $siteBookMarks = $entityManager
            ->getRepository('LpDigital\Bundle\FavoriteBundle\Entity\BookMark')->findBySiteUid($siteUid);
        if (!empty($siteBookMarks)) {
            $category['contents'] = self::processBookMarks($siteBookMarks);
        }
        
        return $category;
    }
    
    /**
     * Process ClassContent in order to appear in the favorite category
     * 
     * @param array $bookMarks
     * @return array
     */
    protected static function processBookMarks(array $bookMarks)
    {
        $categoryContents = [];
        foreach($bookMarks as $bookMark) {
            $classContentClassName = null;
            if (is_string($bookMark)) {
                $classContentClassName = $bookMark;
            } elseif (is_object($bookMark)) {
                $classContentClassName = $bookMark->getBookMarks()[0];
            }
            if(!empty($classContentClassName)) {
                $content = new $classContentClassName;
                $contentArray = [
                    'visible' => true,
                    'label'  => $content->getProperty('name'),
                    'description' => $content->getProperty('description'),
                    'type' => $content->getContentType(),
                    'thumbnail' => './resources/img/contents/'.$content->getImageName()
                ];
                $categoryContents[] = $contentArray;
            }
        }
        
        return $categoryContents;
    }
}
