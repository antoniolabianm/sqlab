# SQLab #

SQLab is a Moodle plugin that adds an Integrated Development Environment (IDE) for PostgreSQL databases. It provides a hands-on learning platform specifically designed for SQL database management and query exercises, enhancing the learning experience for students and offering robust tools for teachers to manage and assess SQL assignments.

**Version**: 4.5.8 - 01 June 2024.

**Author**: José Fernández Carmona, University of Castilla - La Mancha, Spain.

## Features ##

- **Integrated Development Environment (IDE)**: A complete IDE within Moodle that allows students to write and execute SQL queries directly in PostgreSQL.
- **Isolated Environments**: Each student operates within a dedicated database, ensuring that their work is isolated from others, promoting a safe and personalized learning experience.
- **Real-time SQL Execution**: Students can execute SQL commands and see the results immediately, facilitating an interactive and dynamic learning environment.
- **Automatic Grading**: The plugin includes automatic grading capabilities, allowing teachers to set predefined SQL queries used to evaluate student submissions.
- **Error Feedback**: Provides detailed error feedback to help students understand and learn from their mistakes, improving their SQL skills.

## Installation ##

To install SQLab, you have two options:

### Manual Installation
1. Download the latest version of the SQLab plugin.
2. Extract the plugin folder into your Moodle installation at the following location:

    `{your/moodle/dirroot}/mod/`

3. Log in to your Moodle site as an administrator.
4. Navigate to **Site administration > Notifications**. Moodle will automatically detect the plugin and prompt you to complete the installation process.

### Installation via Moodle Plugin Interface
1. Download the SQLab plugin ZIP file.
2. Log in to your Moodle site as an administrator.
3. Navigate to **Site administration > Plugins > Install plugins**.
4. Upload the ZIP file through the provided interface and follow the on-screen instructions to complete the installation.

## Usage ##

Teachers can create assignments or quizzes directly within Moodle using the [SQLQuestion question type](https://github.com/NikolayP12/sqlquestion). SQLab reads these SQLQuestion elements to determine the questions and exercises for each activity. Within SQLQuestion, teachers can configure all the necessary parameters for assessment and exercise creation. This integration ensures that SQLab activities are fully customized to meet the specific learning objectives and assessment criteria set by the teacher.

## License ##

SQLab is released under the GNU General Public License v3.0, which allows you to redistribute and/or modify it under the terms of the GPL. The plugin is provided with NO WARRANTY, without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

See the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html) for more details.

## Contact ##

For more information, contact me:

- **Email**: [Jose.Fdez@alu.uclm.es](mailto:Jose.Fdez@alu.uclm.es)
