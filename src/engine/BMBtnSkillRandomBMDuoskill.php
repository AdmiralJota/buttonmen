<?php
/**
 * BMBtnSkillRandomBMDuoskill: Code specific to RandomBMDuoskill
 *
 * @author: james
 */

/**
 * This class currently supports the special skills of RandomBMDuoskill, which has
 * 4 normal dice and a swing die, and 2 skills each appearing a total of 2 times on various dice.
 */
class BMBtnSkillRandomBMDuoskill extends BMBtnSkillRandomBM {
    /**
     * An array containing the names of functions run by
     * BMCanHaveSkill->run_hooks()
     *
     * @var array
     */
    public static $hooked_methods = array('specify_recipes');

    /**
     * Hooked method applied when specifying recipes
     *
     * @param array $args
     * @return boolean
     */
    public static function specify_recipes(array $args) {
        if (!parent::specify_recipes($args)) {
            return FALSE;
        }

        $skillCharArray = array_merge(array_diff(
            BMSkill::all_skill_chars(),
            self::excluded_skill_char_array()
        ));

        $button = $args['button'];
        $dieSizeArray = array_merge(
            parent::generate_die_sizes(4),
            parent::randomly_select_swing_types()
        );
        $dieSkillLetterArrayArray = parent::generate_die_skills(
            5,
            parent::randomly_select_skills(2, $skillCharArray),
            0,
            2
        );
        $button->recipe = parent::generate_recipe($dieSizeArray, $dieSkillLetterArrayArray);

        return TRUE;
    }

    /**
     * Description of skill
     *
     * @return string
     */
    public static function get_description() {
        return 'Four regular dice and one swing die, and 2 skills each appearing a total of 2 times on various dice.';
    }
}
