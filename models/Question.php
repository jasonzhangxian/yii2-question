<?php
/**
 * @link http://www.tintsoft.com/
 * @copyright Copyright (c) 2012 TintSoft Technology Co. Ltd.
 * @license http://www.tintsoft.com/license/
 */
namespace yuncms\question\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Markdown;
use yii\helpers\Inflector;
use yii\helpers\HtmlPurifier;
use yii\behaviors\AttributeBehavior;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yuncms\tag\behaviors\TagBehavior;
use yuncms\tag\models\Tag;
use yuncms\user\models\User;


/**
 * Question Model
 * @package artkost\qa\models
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $title
 * @property string $alias
 * @property string $content
 * @property integer $answers
 * @property integer $views
 * @property integer $votes
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property array $tags
 */
class Question extends ActiveRecord
{
    /**
     * 草稿
     */
    const STATUS_DRAFT = 0;

    /**
     * 发布
     */
    const STATUS_PUBLISHED = 1;

    /**
     * Markdown processed content
     * @var string
     */
    public $body;

    /**
     * @inheritdoc
     */
    public static function find()
    {
        return new QuestionQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%question_question}}';
    }

    /**
     * 回答数+1
     * @param string $id
     */
    public static function incrementAnswers($id)
    {
        self::updateAllCounters(['answers' => 1], ['id' => $id]);
    }

    /**
     * 回答数 -1
     * @param $id
     */
    public static function decrementAnswers($id)
    {
        self::updateAllCounters(['answers' => -1], ['id' => $id]);
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'alias'
                ],
                'value' => function ($event) {
                    return Inflector::slug($event->sender->title);
                }
            ],
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_AFTER_FIND => 'body'
                ],
                'value' => function ($event) {
                    return HtmlPurifier::process(Markdown::process($event->sender->content, 'gfm'));
                }
            ],
            'tag' => [
                'class' => TagBehavior::className(),
                'tagValuesAsArray' => false,
                'tagRelation' => 'tags',
                'tagValueAttribute' => 'name',
                'tagFrequencyAttribute' => 'frequency',
            ],
            'blameable' => [
                'class' => BlameableBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'user_id',
                ],
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['title', 'content','tagValues'], 'required'],
            ['tagValues', 'safe'],
            ['status', 'default', 'value' => self::STATUS_PUBLISHED],
            ['status', 'in', 'range' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('question', 'ID'),
            'title' => Yii::t('question', 'Title'),
            'alias' => Yii::t('question', 'Alias'),
            'content' => Yii::t('question', 'Content'),
            'tagValues' => Yii::t('question', 'Tags'),
            'status' => Yii::t('question', 'Status'),
        ];
    }

    /**
     * Tag Relation
     * @return $this
     */
    public function getTags()
    {
        return $this->hasMany(Tag::className(), ['id' => 'tag_id'])
            ->viaTable('{{%question_tag}}', ['question_id' => 'id']);
    }

    /**
     * Answer Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getAnswers()
    {
        return $this->hasMany(Answer::className(), ['question_id' => 'id']);
    }

    /**
     * User Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    /**
     * Favorite Relation
     * @return \yii\db\ActiveQueryInterface
     */
    public function getFavorite()
    {
        return $this->hasOne(Favorite::className(), ['question_id' => 'id']);
    }

    /**
     * 是否是作者
     * @return bool
     */
    public function isAuthor()
    {
        return $this->user_id == Yii::$app->user->id;
    }

    /**
     * 是否是草稿
     * @return bool
     */
    public function isDraft()
    {
        return $this->status == Question::STATUS_DRAFT;
    }

    /**
     * 收藏关系
     * @return \yii\db\ActiveQueryInterface
     */
    public function getFavorites()
    {
        return $this->hasMany(Favorite::className(), ['question_id' => 'id']);
    }

    /**
     * 是否已经收藏
     * @param bool $user
     * @return bool
     */
    public function isFavorite($user = false)
    {
        $user = ($user) ? $user : Yii::$app->user;

        return Favorite::find()->where(['user_id' => $user->id, 'question_id' => $this->id])->exists();
    }

    /**
     * 触发收藏
     * @return bool
     */
    public function toggleFavorite()
    {
        if ($this->isFavorite()) {
            return Favorite::Remove($this->id);
        } else {
            return Favorite::Add($this->id);
        }
    }

    /**
     * 问题被收藏的次数
     * @return int|string
     */
    public function getFavoriteCount()
    {
        return $this->hasMany(Favorite::className(), ['question_id' => 'id'])->count();
    }
}