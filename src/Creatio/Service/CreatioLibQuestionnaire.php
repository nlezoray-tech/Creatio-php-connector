<?php
/**
 * CreatioLibQuestionnaire class file
 *
 * Classe abstraite de gestion des questionnaires Creatio (questionnaires, questions, interviews, réponses)
 *
 * PHP Version 7.4
 *
 * @category Service
 * @package  Creatio
 * @author   Nicolas Lézoray <nlezoray@gmail.com>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://api
 */
namespace Nlezoray\Creatio\Service;

use Nlezoray\Creatio\Service\CreatioLib;

abstract class CreatioLibQuestionnaire extends CreatioLib
{
    /**
     * Méthode GetQuestionAnswers, récupération des réponses à une question
     * @param string $QuestionId      UUID de la question
     * @param string $QuestionnaireId UUID du questionnaire
     * @return mixed
     */
    public function GetQuestionAnswers($QuestionId, $QuestionnaireId) {
        
        //Récupération de l'Id de la question dans le questionnaire
        $QuestionInQuestionnaire = $this->getQuestionInQuestionnaire($QuestionId, $QuestionnaireId);

        //Récupération du type de réponse attendu
        $AnswerTypeName = $this->getAnswerTypeName($QuestionInQuestionnaire['GlbAnswerTypeId']);
        //echo "<xmp>".print_r($AnswerTypeName,1)."</xmp>";exit();

        if ($AnswerTypeName != 'Multi choice') {
            //procédure simple choix
            switch($AnswerTypeName) {
                case 'Choice from a list':
                    $field = 'GlbEnumAnswerId';
                    break;
                case 'Date and time':
                    $field = 'GlbDateTimeAnswer';
                    break;
                case 'Time':
                    $field = 'GlbTimeAnswer';
                    break;
                case 'Integer':
                    $field = 'GlbNumericAnswer';
                    break;
                case 'Boolean':
                    $field = 'GlbBooleanAnswer';
                    break;
                case 'Text':
                    $field = 'GlbTextAnswer';
                    break;
                case 'Date':
                    $field = 'GlbDateAnswer';
                    break;
                case 'Decimal':
                    $field = 'GlbDecimalAnswer';
                    break;
            }
            return $this->getSimpleAnsweredQuestion($QuestionInQuestionnaire['Id'], $field);
        } else {
            //procédure multi choix 
            return $this->getMultiAnsweredQuestion($QuestionInQuestionnaire['Id'], $QuestionnaireId); //field=Multi choice
        }
    }

    /**
     * Méthode getSimpleAnsweredQuestion, il n'y a qu'une réponse possible pour la question du questionnaire
     *
     * @return mixed
     */
    public function getSimpleAnsweredQuestion($QuestionInQuestionnaireId, $field) {
        $api_query = array( '$select' => $field.',GlbInterviewId',
                            '$filter' => urlencode("GlbQuestionInQuestionnaire/Id eq guid'".$QuestionInQuestionnaireId."'") );
        $results = json_decode($this->adapter->get('GlbAnsweredQuestionCollection',$api_query), true);
        //echo "<xmp>".print_r($results,1)."</xmp>";exit();

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0) {
            $response=array();
            $i=0;
            foreach($results['d']['results'] as $result) {

                if ($field != 'GlbEnumAnswerId') {
                    $answer = $result[$field];
                } else {
                    $answer = $this->initAnswerById($result[$field]);
                }

                if (empty($answer)) { //si la réponse est vide on passe à l'enregistrement suivant
                    continue;
                }
                
                if ($field != 'GlbDateAnswer') {
                    $response[$i]['Answer'] = $answer;
                } else {
                    $dateReponse = $this->formatCreatiooDataDate($answer)->format('d-m-Y');
                    
                    if ($dateReponse == '01-01-0001') 
                        continue;
                    else 
                        $response[$i]['Answer'] = $dateReponse;
                }
                
                if ($field == 'GlbBooleanAnswer') {
                    ($answer==true) ? $answer = 'OUI' : $answer = 'NON';
                    $response[$i]['Answer'] = $answer;
                }

                $interview = $this->initInterviewById($result['GlbInterviewId']);

                if(!$this->IsGuidEmpty($interview['GlbAccountId'])) {
                    $response[$i]['AccountId'] = $interview['GlbAccountId']; 
                    $response[$i]['AccountName'] = $this->initAccountNameById($interview['GlbAccountId']); 
                } else {
                    $response[$i]['AccountId'] = ''; 
                    $response[$i]['AccountName'] = ''; 
                }

                if(!$this->IsGuidEmpty($interview['GlbContactId'])) {
                    $response[$i]['ContactId'] = $interview['GlbContactId']; 
                    $response[$i]['ContactName'] = $this->initContactNameById($interview['GlbContactId']); 
                } else {
                    $response[$i]['ContactId'] = ''; 
                    $response[$i]['ContactName'] = ''; 
                }

                if(!$this->IsGuidEmpty($interview['UsrOpportunityId'])) {                
                    $response[$i]['OpportunityId'] = $interview['UsrOpportunityId']; 
                    $response[$i]['OpportunityName'] = $this->initOpportunityNameById($interview['UsrOpportunityId']); 
                } else {
                    $response[$i]['OpportunityId'] = ''; 
                    $response[$i]['OpportunityName'] = ''; 
                }

                $i++;
                //if($i==25) break;
            }
            return $response;
        } else {
            return '';
        }      
    }


    /**
     * Méthode getMultiAnsweredQuestion, plusieurs réponses sont possibles pour la question du questionnaire
     *
     * @return mixed
     */
    public function getMultiAnsweredQuestion($QuestionInQuestionnaireId, $QuestionnaireId) {
        //Liste des interviews pour le questionnaire
        $Interviews = $this->getQuestionnaireInterviews($QuestionnaireId);

        $i=0;
        $AnsweredChoices = [];
        foreach ($Interviews as $Interview) {
            
            //on récupère la réponse à la question pour l'interview
            $AnsweredQuestion[$i] = $this->getAnsweredQuestion($QuestionInQuestionnaireId,$Interview['Id']);

            if (!isset($AnsweredQuestion[$i]) || !is_array($AnsweredQuestion[$i]) || count($AnsweredQuestion[$i]) === 0) {
                continue;
            } else {

                //Est-ce qu'il y a eu des réponses choisies
                $AnsweredChoices[$i] = $this->getAnsweredChoices($AnsweredQuestion[$i]['Id'],$Interview['Id']);

                if( sizeof($AnsweredChoices[$i])==0 ) { //Pas de réponse
                    continue; 
                } else {
                    $j=0;
                    foreach($AnsweredChoices[$i] as $AnsweredChoice) {
                        if ($AnsweredChoice['GlbIsChecked']==true) {
                            $AnswerInQuestion = $this->initAnswerInQuestion($AnsweredChoice['GlbAnswerInQuestionId']);
                            $response[$i]['reponses'][$j] = $this->initAnswerById($AnswerInQuestion);
                            $j++;
                        }
                    }

                    $interview = $this->initInterviewById($Interview['Id']);

                    if(!$this->IsGuidEmpty($interview['GlbAccountId'])) {
                        $response[$i]['AccountId'] = $interview['GlbAccountId']; 
                        $response[$i]['AccountName'] = $this->initAccountNameById($interview['GlbAccountId']); 
                    } else {
                        $response[$i]['AccountId'] = ''; 
                        $response[$i]['AccountName'] = ''; 
                    }

                    if(!$this->IsGuidEmpty($interview['GlbContactId'])) {
                        $response[$i]['ContactId'] = $interview['GlbContactId']; 
                        $response[$i]['ContactName'] = $this->initContactNameById($interview['GlbContactId']); 
                    } else {
                        $response[$i]['ContactId'] = ''; 
                        $response[$i]['ContactName'] = ''; 
                    }

                    if(!$this->IsGuidEmpty($interview['UsrOpportunityId'])) {                
                        $response[$i]['OpportunityId'] = $interview['UsrOpportunityId']; 
                        $response[$i]['OpportunityName'] = $this->initOpportunityNameById($interview['UsrOpportunityId']); 
                    } else {
                        $response[$i]['OpportunityId'] = ''; 
                        $response[$i]['OpportunityName'] = ''; 
                    }

                }
                $i++;
                //if($i==25) break;
            }
        }
        return $response;
    }
    
    /**
     * Méthode listQuestionnaires, retourne la liste des questionnaire Creatio
     *
     * @return mixed
     */
    public function listQuestionnaires() {
        
        $api_query = array( '$select'=>'Id,GlbName' );
        $results = json_decode($this->adapter->get('GlbQuestionnaireCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'];
        else
            return '';

    }

    /**
     * Méthode getQuestionnaireInterviews, retourne la liste des interviews pour le questionnaire
     *
     * @return mixed
     */
    public function getQuestionnaireInterviews($QuestionnaireId) {
        
        $api_query = array( '$select'=>'Id,GlbName',
                            '$filter' => urlencode("GlbQuestionnaire/Id eq guid'".$QuestionnaireId."'"));
        $results = json_decode($this->adapter->get('GlbInterviewCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'];
        else
            return '';

    }     
    
    /**
     * Méthode initQuestionnaireById.
     *
     * @return mixed
     */
    public function initQuestionnaireById($id) {
        
        $api_query = array( '$select'=>'GlbName',
                            '$filter' => urlencode("Id eq guid'".$id."'") );
        $result = json_decode($this->adapter->get('GlbQuestionnaireCollection',$api_query), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0];
        } else {
            return '';
        }  

    }
    
    /**
     * Méthode listQuestionnaireQuestions.
     *
     * @return mixed
     */
    public function listQuestionnaireQuestions($questionnaireId) {
        
        $api_query = array( '$select'=>'Id,GlbQuestionId,Position,GlbAnswerTypeId',
                            '$filter' => urlencode("GlbQuestionnaire/Id eq guid'".$questionnaireId."'"),
                            '$orderby' => 'Position');
        $results = json_decode($this->adapter->get('GlbQuestionInQuestionnaireCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0) {
            $response = array();
            $i=0;
            foreach($results['d']['results'] as $result) {
                $response[$i]['AnswerType'] = $this->getAnswerTypeName($result['GlbAnswerTypeId']);
                $response[$i]['Id']   = $result['GlbQuestionId'];
                $response[$i]['QuestionnaireId']   = $questionnaireId;
                $response[$i]['Name'] = $this->getQuestionName($result['GlbQuestionId']);
                $response[$i]['Position']   = $result['Position'];
                $i++;
            }
            return $response;
        } else
            return '';

    }

    /**
     * Méthode ListQuestionnaireInterviews.
     *
     * @return mixed
     */
    public function ListQuestionnaireInterviews($Id, $page=1) {
        
        $nbresultparpage = 20;
        $skip = ($page-1) * $nbresultparpage;

        $api_query = array( '$select'=>'Id,GlbAccountId,GlbCompleted,GlbContactId,GlbName,GlbStarted,ModifiedOn,ModifiedBy,UsrOpportunityId',
                            '$filter' => urlencode("GlbQuestionnaire/Id eq guid'".$Id."'"));
        $results = json_decode($this->adapter->get('GlbInterviewCollection',$api_query, $nbresultparpage, 0, 'ModifiedOn', $skip), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0) {
            $response = array();
            $i=0;
            foreach($results['d']['results'] as $result) {

                $response[$i]['Id']               = $result['Id'];
                $response[$i]['GlbName']          = $result['GlbName'];
                $response[$i]['GlbStarted']       = $result['GlbStarted'];
                $response[$i]['GlbCompleted']     = $result['GlbCompleted'];
                $response[$i]['GlbAccountId']     = $result['GlbAccountId'];
                $response[$i]['GlbAccountName']   = $this->initAccountNameById($result['GlbAccountId']);
                $response[$i]['GlbContactId']     = $result['GlbContactId'];

                if ($result['GlbContactId'] != '00000000-0000-0000-0000-000000000000')
                    $response[$i]['GlbContactName']   = $this->initContactNameById($result['GlbContactId']);
                else 
                    $response[$i]['GlbContactName']   = '';

                $response[$i]['UsrOpportunityId'] = $result['UsrOpportunityId'];
                $response[$i]['ModifiedOn']       = $this->formatCreatiooDataDate($result['ModifiedOn']);

                $i++;
            }

            return $response;

        } else
            return '';

    }  

    /**
     * Méthode initQuestionById.
     *
     * @return mixed
     */
    public function initQuestionById($QuestionId) {
        
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$QuestionId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0) {
            $response = array();
            $response['Id'] = $QuestionId;
            $response['Name'] = $results['d']['results'][0]['Name'];
            return $response;
        } else
            return '';
    }

    /**
     * Méthode getQuestionInQuestionnaire.
     *
     * @return mixed
     */
    public function getQuestionInQuestionnaire($QuestionId, $QuestionnaireId) {
        $api_query = array( '$select'=>'Id,GlbAnswerTypeId,GlbIsList',
                            '$filter' => urlencode("GlbQuestion/Id eq guid'".$QuestionId."' and GlbQuestionnaire/Id eq guid'".$QuestionnaireId."'") );
        $result = json_decode($this->adapter->get('GlbQuestionInQuestionnaireCollection',$api_query), true);
        //echo "<xmp>".print_r($result,1)."</xmp>";exit();

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0];
        } else {
            return '';
        }        
    }

    /**
     * Méthode getAnsweredQuestion.
     *
     * @return mixed
     */
    public function getAnsweredQuestion($questionInQuestionnaireId, $InterviewId) {

        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("GlbQuestionInQuestionnaire/Id eq guid'".$questionInQuestionnaireId."' and GlbInterview/Id eq guid'".$InterviewId."'") );
        $results = json_decode($this->adapter->get('GlbAnsweredQuestionCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0];
        else
            return '';

    }

    /**
     * Méthode getAnsweredChoices.
     *
     * @return mixed
     */
    public function getAnsweredChoices($AnsweredQuestionId, $InterviewId) {
        
        $api_query = array( '$select'=>'Id,GlbInterviewId,GlbAnsweredQuestionId,GlbAnswerInQuestionId,GlbIsChecked',
                            '$filter' => urlencode("GlbAnsweredQuestion/Id eq guid'".$AnsweredQuestionId."' and GlbInterview/Id eq guid'".$InterviewId."'") );
        $results = json_decode($this->adapter->get('GlbAnsweredChoiceCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'];
        else
            return [];

    }    

    /**
     * Méthode initAnswerInQuestion.
     *
     * @return mixed
     */
    public function initAnswerInQuestion($Id) {
        
        $api_query = array( '$select'=>'GlbAnswerId',
                            '$filter' => urlencode("Id eq guid'".$Id."'"));
        $result = json_decode($this->adapter->get('GlbAnswerInQuestionCollection',$api_query), true);
        //echo "<xmp>".print_r($result['d']['results'][0],1)."</xmp>";exit();

        if (isset($result['d']['results'][0])) 
            return $result['d']['results'][0]['GlbAnswerId'];
        else
            return '';

    }

    function IsGuidEmpty($str){
        if ($str === '00000000-0000-0000-0000-000000000000')
            return true;
        else
            return false;
    }
    
    /**
     * Méthode initInterviewById.
     *
     * @return mixed
     */
    public function initInterviewById($Id) {
        $api_query = array('$select'=>'GlbName,GlbAccountId,GlbContactId,UsrOpportunityId,CreatedOn,CreatedById,ModifiedOn,ModifiedById',
            '$filter' => urlencode("Id eq guid'".$Id."'")
        );
        $result = json_decode($this->adapter->get('GlbInterviewCollection', $api_query), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0];
        } else {
            return '';
        }
    }
    
    /**
     * Méthode initAccountNameById.
     *
     * @return mixed
     */
    public function initAccountNameById($Id) {
        $api_query = array('$select'=>'Name',
                           '$filter' => urlencode("Id eq guid'".$Id."'")
        );
        $result = json_decode($this->adapter->get('AccountCollection', $api_query), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0]['Name'];
        } else {
            return '';
        }
    }

    /**
     * Méthode initAnswerById.
     *
     * @return mixed
     */
    public function initAnswerById($Id) {
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$Id."'") );
        $result = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true);   

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0]['Name'];
        } else {
            return '';
        }       
    }
    
    /**
     * Méthode initContactNameById.
     *
     * @return mixed
     */
    public function initContactNameById($Id) {
        $api_query = array('$select'=>'Name',
            '$filter' => urlencode("Id eq guid'".$Id."'")
        );
        $result = json_decode($this->adapter->get('ContactCollection', $api_query), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0]['Name'];
        } else {
            return '';
        }
    }
    
    /**
     * Méthode initOpportunityNameById.
     *
     * @return mixed
     */
    public function initOpportunityNameById($Id) {
        $api_query = array('$select'=>'Title',
            '$filter' => urlencode("Id eq guid'".$Id."'")
        );
        $result = json_decode($this->adapter->get('OpportunityCollection', $api_query), true);

        if (isset($result['d']['results'][0])) {
            return $result['d']['results'][0]['Title'];
        } else {
            return '';
        }
    }

    /**
     * Méthode formatCreatiooDataDate.
     *
     * @return mixed
     */
    public function formatCreatiooDataDate($varDate) {
        $strDate = \DateTime::createFromFormat('d/m/Y', date('d/m/Y', substr(str_replace(')/','',str_replace('/Date(','',$varDate)),0,-3)));
        return $strDate;
    }    
    
    /**
     * Méthode listeDesQuestionsDuQuestionnaire.
     *
     * @return mixed
     */
    public function listeDesQuestionsDuQuestionnaire($QuestionnaireId) {
        
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("GlbQuestionnaire/Id eq guid'".$QuestionnaireId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionInQuestionnaireCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'];
        else
            return '';

    }
   
    /**
     * Méthode CreateInterview.
     *
     * @return mixed
     */
    public function CreateInterview($TypeQuestionnaireId, $QuestionnaireId, $item) {
        $tabObject = array( 'GlbName'                => 'Reprise externalisation',
                            'GlbQuestionnaireId'     => $QuestionnaireId,
                            'GlbQuestionnaireTypeId' => $TypeQuestionnaireId,
                            'GlbAccountId'  => $item['accountId'],
                            'GlbStarted'    => true,
                            'GlbCompleted'  => false,
                            'ModifiedOn'    => $item['ModifiedOn'],
                            'ModifiedById'  => $item['ModifiedById']
                        );
        $result = json_decode($this->adapter->post('GlbInterviewCollection', $tabObject),1);
        return $this->checkInsertRetour($result);
    }  

    /**
     * Méthode getAnsweredQuestionId.
     *
     * @return mixed
     */
    public function getAnsweredQuestionId($QuestionName, $questionnaireId, $InterviewId) {

        //On va chercher l'Id de la question correspondant au nom
        $QuestionId = $this->getQuestionIdByName($QuestionName);
        
        //Puis on va chercher l'Id de la question dans le questionnaire
        $QuestionInQuestionnaireId = $this->getQuestionInQuestionnaireId($QuestionId, $questionnaireId);

        //Enfin on ramène l'Id de la réponse à mettre à jour
        $AnsweredQuestionId = $this->getAnsweredQuestionIdByKeys($QuestionInQuestionnaireId, $InterviewId);

        return array(
            'QuestionId' => $QuestionId,
            'QuestionInQuestionnaireId' => $QuestionInQuestionnaireId,
            'AnsweredQuestionId' => $AnsweredQuestionId
        );
    }

    /**
     * Méthode updateAnsweredQuestion.
     *
     * @return mixed
     */
    public function updateAnsweredQuestion($AnsweredQuestionId, $field, $Reponse) {

        $tabObject = array( 'Id' => $AnsweredQuestionId,
                            $field => $Reponse
                        );
        $result = json_decode($this->adapter->put('GlbAnsweredQuestionCollection', $AnsweredQuestionId ,$tabObject), true);

        return $this->checkUpdateRetour($result);
    }

    /**
     * Méthode getAnswerConcurrentIdByExternalisationConcurrentId.
     *
     * @return mixed
     */
    public function getAnswerConcurrentIdByExternalisationConcurrentId($Objet, $ConcurrentExternalisationConnectId, $QuestionInQuestionnaireId) {
        //Récupération du nom du concurrent de UsrExternalisationConcurrentConnect
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$ConcurrentExternalisationConnectId."'") );
        $results = json_decode($this->adapter->get($Objet,$api_query), true);        
        $ConcurrentName = $results['d']['results'][0]['Name'];
        
        //On va chercher la liste des réponses correspondants au concurrent
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".addslashes($ConcurrentName)."'") );
        $results = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true);  
        
        //il faut parcourrir les réponses pour trouver celle correspondant au questionnaire
        foreach ($results['d']['results'] as $result) {
            $api_query = array( '$select'=>'GlbAnswerId',
                                '$filter' => urlencode("GlbAnswer/Id eq guid'".$result['Id']."' and GlbQuestionInQuestionnaire/Id eq guid'".$QuestionInQuestionnaireId."'") );
            $resultat = json_decode($this->adapter->get('GlbAnswerInQuestionCollection',$api_query), true);

            if (isset($resultat['d']['results'])) { 
                return $resultat['d']['results'][0]['GlbAnswerId'];
            }
        }
        
        return false;
    }

    /**
     * Méthode getSatisfactionAnswerInQuestionId.
     *
     * @return mixed
     */
    public function getSatisfactionAnswerInQuestionId($Objet, $reponseId, $QuestionInQuestionnaireId) {
        //On va chercher le nom de la réponse de UsrExternalisation
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$reponseId."'") );
        $results = json_decode($this->adapter->get($Objet, $api_query), true); 
        
        //On ramène ensuite l'Id de GlbAnswer correspondant
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".$results['d']['results'][0]['Name']."'") );
        $results = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true); 

        //et finalement on ramène l'Id de la Réponse dans les questions
        foreach ($results['d']['results'] as $result) {
            $api_query = array( '$select'=>'Id',
                                '$filter' => urlencode("GlbAnswer/Id eq guid'".$result['Id']."' and GlbQuestionInQuestionnaire/Id eq guid'".$QuestionInQuestionnaireId."'") );
            $resultat = json_decode($this->adapter->get('GlbAnswerInQuestionCollection',$api_query), true);

            if (isset($resultat['d']['results']) && sizeof($resultat['d']['results'])>0) {
                return $resultat['d']['results'][0]['Id'];
            }        
        }
        return false;
    }

    /**
     * Méthode getAnswerInQuestionId.
     *
     * @return mixed
     */
    public function getAnswerInQuestionId($Reponse, $QuestionInQuestionnaireId) {
        //On ramène l'Id de GlbAnswer correspondant à la réponse
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".str_replace("'","''",$Reponse)."'") );
        $results = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true);

        //et finalement on ramène l'Id de la Réponse dans les questions
        foreach ($results['d']['results'] as $result) {
            $api_query = array( '$select'=>'Id',
                                '$filter' => urlencode("GlbAnswer/Id eq guid'".$result['Id']."' and GlbQuestionInQuestionnaire/Id eq guid'".$QuestionInQuestionnaireId."'") );
            $resultat = json_decode($this->adapter->get('GlbAnswerInQuestionCollection',$api_query), true);

            if (isset($resultat['d']['results']) && sizeof($resultat['d']['results'])>0) {
                return $resultat['d']['results'][0]['Id'];
            }        
        }
        return false;
    }

    /**
     * Méthode insertOrUpdateAnsweredChoice.
     *
     * @return mixed
     */
    public function insertOrUpdateAnsweredChoice($AnswerInQuestionId, $AnsweredQuestionId, $InterviewId) {
        //Y a t'il déjà une case à cocher existante ?
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("GlbInterview/Id eq guid'".$InterviewId."' and GlbAnsweredQuestion/Id eq guid'".$AnsweredQuestionId."' and GlbAnswerInQuestion/Id eq guid'".$AnswerInQuestionId."'") );
        $results = json_decode($this->adapter->get('GlbAnsweredChoiceCollection',$api_query), true); 
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0) {
            //Mise à jour de la case à cocher à True
            return $this->updateAnsweredChoiceById($results['d']['results'][0]['Id'], true);
        } else {
            //Création de la case à cocher à true
            return $this->insertAnsweredChoice($AnswerInQuestionId, $AnsweredQuestionId, $InterviewId, true);
        }
    }

    function updateAnsweredChoiceById($Id, $GlbIsChecked) {
        $tabObject = array( 'GlbIsChecked'=> $GlbIsChecked );
        $result = json_decode($this->adapter->put('GlbAnsweredChoiceCollection', $Id, $tabObject), true);
        
        return $this->checkUpdateRetour($result);
    }

    function insertAnsweredChoice($AnswerInQuestionId, $AnsweredQuestionId, $InterviewId, $GlbIsChecked=true) {
        $tabObject = array( 'GlbAnswerInQuestionId'=> $AnswerInQuestionId,
                            'GlbAnsweredQuestionId' => $AnsweredQuestionId,
                            'GlbInterviewId' => $InterviewId,
                            'GlbIsChecked' => $GlbIsChecked,
                          );
        $result = json_decode($this->adapter->post('GlbAnsweredChoiceCollection', $tabObject), true);
        
        return $this->checkInsertRetour($result);
    }

    /**
     * Méthode getAnswerChoiceSatisfactionIdByExternalisationSatisfactionId.
     *
     * @return mixed
     */
    public function getAnswerChoiceSatisfactionIdByExternalisationSatisfactionId($SatisfactionConnectId, $QuestionInQuestionnaireId) {
        //Récupération du nom du concurrent de UsrExternalisationConcurrentConnect
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$SatisfactionConnectId."'") );
        $results = json_decode($this->adapter->get('UsrExternalisationSatisfactionConnectCollection',$api_query), true);        
        $SatisfactionName = $results['d']['results'][0]['Name'];
        
        //On va chercher la liste des réponses correspondants à la satisfaction de l'externalisation
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".$SatisfactionName."'") );
        $results = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true);  
        
        //il faut parcourrir les réponses pour trouver celle correspondant au questionnaire
        foreach ($results['d']['results'] as $result) {
            $api_query = array( '$select'=>'GlbAnswerId',
                                '$filter' => urlencode("GlbAnswer/Id eq guid'".$result['Id']."' and GlbQuestionInQuestionnaire/Id eq guid'".$QuestionInQuestionnaireId."'") );
            $resultat = json_decode($this->adapter->get('GlbAnswerInQuestionCollection',$api_query), true);

            if (isset($resultat['d']['results'])) { 
                return $resultat['d']['results'][0]['GlbAnswerId'];
            }
        }
        
        return false;
    }

    /**
     * Méthode insertEmptyAnsweredQuestions.
     *
     * @return mixed
     */
    public function insertEmptyAnsweredQuestions($QuestionnaireId, $InterviewId) {
        $listeDesQuestion = $this->listeDesQuestionsDuQuestionnaire($QuestionnaireId);
        
        foreach ($listeDesQuestion as $QuestionInQuestionnaire) {
            $this->insertEmptyAnsweredQuestion($QuestionInQuestionnaire['Id'], $InterviewId);
        }

        return true;
    }
    
    /**
     * Méthode getObjetAnswerValue.
     *
     * @return mixed
     */
    public function getObjetAnswerValue($objet, $answerId) {
        
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$answerId."'") );
        $results = json_decode($this->adapter->get($objet.'Collection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Name'];
        else
            return '';

    }

    /**
     * Méthode insertEmptyAnsweredQuestion.
     *
     * @return mixed
     */
    public function insertEmptyAnsweredQuestion($QuestionInQuestionnaire, $InterviewId) {
        $tabObject = array( 'GlbQuestionInQuestionnaireId' => $QuestionInQuestionnaire,
                            'GlbInterviewId' => $InterviewId
                        );
        $result = json_decode($this->adapter->post('GlbAnsweredQuestionCollection', $tabObject),1);

        return $result['d']['Id'];
    }

    /**
     * Méthode getAnsweredQuestionIdByKeys.
     *
     * @return mixed
     */
    public function getAnsweredQuestionIdByKeys($questionInQuestionnaireId, $InterviewId) {

        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("GlbQuestionInQuestionnaire/Id eq guid'".$questionInQuestionnaireId."' and GlbInterview/Id eq guid'".$InterviewId."'") );
        $results = json_decode($this->adapter->get('GlbAnsweredQuestionCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';

    }

    /**
     * Méthode getConcurrentExternalisationConnectName.
     *
     * @return mixed
     */
    public function getConcurrentExternalisationConnectName($id) {
        
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$id."'") );
        $results = json_decode($this->adapter->get('UsrExternalisationConcurrentConnectCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Name'];
        else
            return '';

    }

    /**
     * Méthode getConcurrentConnectIdByName.
     *
     * @return mixed
     */
    public function getConcurrentConnectIdByName($Name) {
        
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".$Name."'") );
        $results = json_decode($this->adapter->get('GlbAnswerCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }

    /**
     * Méthode getListeUsrExternalisationToProcess.
     *
     * @return mixed
     */
    public function getListeUsrExternalisationToProcess($nbresult) {
        
        $api_query = array( '$select'=>'Id,accountId,Typeprojetexternalisation,CreatedOn,CreatedById,ModifiedOn,ModifiedById,etudieoffre,democheck,note,etatetudieoffre,etatprojetexternalisation,ConcurrentExternalisationConnectId,SousTotalSortantHorsColis,CumulEntrantSortant,TotalEntrant,TotalSortant,VolumeEntrantColis,VolumeEntrantReco,VolumeEntrantSimple,VolumeSortantColis,VolumeSortantReco,VolumeSortantSimple,TypeDestinataireExternalisationId,NombreServicesConnectId,NombreUtilisateursConnectId,UsrExternalisationSatisfactionConnectId,dateFinAbonnementTrackingEntrant,dateFinAbonnementTrackingSortant,multiSiteConnect,dateFinAbonnementConnect,UsrExternalisationSatisfactionTrackingEntrantId,UsrExternalisationSatisfactionTrackingSortantId,ConcurrentExternalisationTrackingEntrantId,ConcurrentExternalisationTrackingSortantId,Notes,methodeEnvoiAffranchiPrestataire,methodeEnvoiAutreMethode,methodeEnvoiEnvoiElectronique,methodeEnvoiExternalisation,methodeEnvoiTimbresMA,NoteConnect,NoteTracking,TotalEntrantPreQualif,TotalSortantPreQualif,Processed',
                            '$filter' => urlencode("Processed eq false") 
                        );
        $results = json_decode($this->adapter->get('UsrExternalisationCollection',$api_query, $nbresult), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'];
        else
            return '';
    }   

    /**
     * Méthode testTimeOut.
     *
     * @return mixed
     */
    public function testTimeOut($UsrExternalisationId) {
        
        $api_query = array( '$select'=>'Id,accountId,Typeprojetexternalisation,CreatedOn,CreatedById,ModifiedOn,ModifiedById,etudieoffre,democheck,note,etatetudieoffre,etatprojetexternalisation,ConcurrentExternalisationConnectId,SousTotalSortantHorsColis,CumulEntrantSortant,TotalEntrant,TotalSortant,VolumeEntrantColis,VolumeEntrantReco,VolumeEntrantSimple,VolumeSortantColis,VolumeSortantReco,VolumeSortantSimple,TypeDestinataireExternalisationId,NombreServicesConnectId,NombreUtilisateursConnectId,UsrExternalisationSatisfactionConnectId,dateFinAbonnementTrackingEntrant,dateFinAbonnementTrackingSortant,multiSiteConnect,dateFinAbonnementConnect,UsrExternalisationSatisfactionTrackingEntrantId,UsrExternalisationSatisfactionTrackingSortantId,ConcurrentExternalisationTrackingEntrantId,ConcurrentExternalisationTrackingSortantId,Notes,methodeEnvoiAffranchiPrestataire,methodeEnvoiAutreMethode,methodeEnvoiEnvoiElectronique,methodeEnvoiExternalisation,methodeEnvoiTimbresMA,NoteConnect,NoteTracking,TotalEntrantPreQualif,TotalSortantPreQualif,Processed',
                            '$filter' => urlencode("Id eq guid'".$UsrExternalisationId."'") );
        $results = json_decode($this->adapter->get('UsrExternalisationCollection',$api_query, 1), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0];
        else
            return '';
    } 

    /**
     * Méthode getUsrExternalisationById.
     *
     * @return mixed
     */
    public function getUsrExternalisationById($UsrExternalisationId) {
        
        $api_query = array( '$select'=>'Id,accountId,Typeprojetexternalisation,CreatedOn,CreatedById,ModifiedOn,ModifiedById,etudieoffre,democheck,note,etatetudieoffre,etatprojetexternalisation,ConcurrentExternalisationConnectId,SousTotalSortantHorsColis,CumulEntrantSortant,TotalEntrant,TotalSortant,VolumeEntrantColis,VolumeEntrantReco,VolumeEntrantSimple,VolumeSortantColis,VolumeSortantReco,VolumeSortantSimple,TypeDestinataireExternalisationId,NombreServicesConnectId,NombreUtilisateursConnectId,UsrExternalisationSatisfactionConnectId,dateFinAbonnementTrackingEntrant,dateFinAbonnementTrackingSortant,multiSiteConnect,dateFinAbonnementConnect,UsrExternalisationSatisfactionTrackingEntrantId,UsrExternalisationSatisfactionTrackingSortantId,ConcurrentExternalisationTrackingEntrantId,ConcurrentExternalisationTrackingSortantId,Notes,methodeEnvoiAffranchiPrestataire,methodeEnvoiAutreMethode,methodeEnvoiEnvoiElectronique,methodeEnvoiExternalisation,methodeEnvoiTimbresMA,NoteConnect,NoteTracking,TotalEntrantPreQualif,TotalSortantPreQualif,Processed',
                            '$filter' => urlencode("Id eq guid'".$UsrExternalisationId."'") );
        $results = json_decode($this->adapter->get('UsrExternalisationCollection',$api_query, 1), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0];
        else
            return '';
    }   

    /**
     * Méthode GetQuestionnaireTypeId.
     *
     * @return mixed
     */
    public function GetQuestionnaireTypeId($QuestionnaireTypeName) {
        
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".$QuestionnaireTypeName."'") );
        $results = json_decode($this->adapter->get('GlbQuestionnaireTypeCollection',$api_query), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }  

    /**
     * Méthode GetQuestionnaireNameById.
     *
     * @return mixed
     */
    public function GetQuestionnaireNameById($QuestionnaireId) {
        
        $api_query = array( '$select'=>'GlbName',
                            '$filter' => urlencode("Id eq guid'".$QuestionnaireId."' and") );
        $results = json_decode($this->adapter->get('GlbQuestionnaireCollection',$api_query), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['GlbName'];
        else
            return '';
    }   

    /**
     * Méthode GetQuestionnaireId.
     *
     * @return mixed
     */
    public function GetQuestionnaireId($QuestionnaireName, $QuestionnaireTypeId) {
        
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("GlbName eq '".$QuestionnaireName."' and GlbQuestionnaireType/Id eq guid'".$QuestionnaireTypeId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionnaireCollection',$api_query), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }   

    /**
     * Méthode getAnswerTypeId.
     *
     * @return mixed
     */
    public function getAnswerTypeId($QuestionInQuestionnaireId) {
        
        $api_query = array( '$select'=>'GlbAnswerTypeId',
                            '$filter' => urlencode("Id eq guid'".$QuestionInQuestionnaireId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionInQuestionnaireCollection',$api_query), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['GlbAnswerTypeId'];
        else
            return '';
    }     

    /**
     * Méthode getAnswerTypeName.
     *
     * @return mixed
     */
    public function getAnswerTypeName($AnswerTypeId) {
        
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$AnswerTypeId."'") );
        $results = json_decode($this->adapter->get('GlbAnswerTypeCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Name'];
        else
            return '';
    }

    /**
     * Méthode getQuestionIdByName.
     *
     * @return mixed
     */
    public function getQuestionIdByName($Name) {
        
        $api_query = array( '$select'=>'Id',
                            '$filter' => urlencode("Name eq '".str_replace("'","''",$Name)."'") );
        $results = json_decode($this->adapter->get('GlbQuestionCollection',$api_query), true);
        
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }     

    /**
     * Méthode getQuestionName.
     *
     * @return mixed
     */
    public function getQuestionName($QuestionId) {
        
        $api_query = array( '$select'=>'Name',
                            '$filter' => urlencode("Id eq guid'".$QuestionId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Name'];
        else
            return '';
    }

    /**
     * Méthode GetQuestionnaireTypeIdByQuestionnaireId.
     *
     * @return mixed
     */
    public function GetQuestionnaireTypeIdByQuestionnaireId($QuestionnaireId) {
        
        $api_query = array( '$select'=>'GlbQuestionnaireTypeId',
                            '$filter' => urlencode("Id eq guid'".$QuestionnaireId."'") );

        $results = json_decode($this->adapter->get('GlbQuestionnaireCollection',$api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['GlbQuestionnaireTypeId'];
        else
            return '';
    }

    /**
     * Méthode getQuestionInQuestionnaireId.
     *
     * @return mixed
     */
    public function getQuestionInQuestionnaireId($questionId, $QuestionnaireId) {
        
        $api_query = array( '$select'=> 'Id', 
                            '$filter' => urlencode("GlbQuestionnaire/Id eq guid'".$QuestionnaireId."' and GlbQuestion/Id eq guid'".$questionId."'") );
        $results = json_decode($this->adapter->get('GlbQuestionInQuestionnaireCollection', $api_query), true);

        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }
   
    /**
     * Méthode setUsrExternalisationProcessed.
     *
     * @return mixed
     */
    public function setUsrExternalisationProcessed($UsrExternalisationId) {
        $tabObject = array( 'Processed'=> true);
        $result = $this->adapter->put('UsrExternalisationCollection', $UsrExternalisationId, $tabObject);
        
        return $this->checkUpdateRetour($result);
    } 

    /**
     * Méthode checkGetRetour.
     *
     * @return mixed
     */
    public function checkGetRetour($result) {
        if (isset($results['d']['results']) && sizeof($results['d']['results'])>0)
            return $results['d']['results'][0]['Id'];
        else
            return '';
    }

    /**
     * Méthode checkUpdateRetour.
     *
     * @return mixed
     */
    public function checkUpdateRetour($result) {
        if (isset($result['error'])) {
            $retour['code'] = false;
            $retour['message']= $result['error']['message']['value'];
            return $retour;
        } else {
            $retour['code'] = true;
            return $retour;
        }
    }

    /**
     * Méthode checkInsertRetour.
     *
     * @return mixed
     */
    public function checkInsertRetour($result) {
        if (isset($result['error'])) {
            $retour['code'] = false;
            $retour['message']= $result['error']['message']['value'];
            return $retour;
        } else {
            $retour['code'] = true;
            $retour['Id'] = $result['d']['Id'];
            return $retour;
        }
    }
}