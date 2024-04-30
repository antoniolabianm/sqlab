# SQLab #

SQLab is an educational tool for Moodle that enables instructors and students to interact with PostgreSQL databases. It provides a hands-on learning platform similar to tools like CodeRunner or VPL, but is specifically designed for SQL database management and querying exercises.

**Version**: 4.5.7 - 30 April 2024.

**Author**: José Fernández Carmona, University of Castilla - La Mancha, Spain.

## Features ##

- **Isolated Environments**: Each student works within a dedicated schema, ensuring that their work is sandboxed from others.
- **Real-time SQL Execution**: Students can execute SQL commands and see immediate results, enhancing the learning experience.
- **Automatic Grading**: The plugin supports automatic grading based on predefined correct SQL queries.
- **Error Handling**: Provides robust error feedback to help students learn from their mistakes.

## Installation ##

To install SQLab, you have two options:

### Manual Installation
1. Download the latest version of the SQLab plugin.
2. Extract the plugin folder into your Moodle installation at the following location:

    {your/moodle/dirroot}/mod/

3. Log in to your Moodle site as an administrator.
4. Navigate to **Site administration > Notifications**. Moodle will automatically detect the plugin and prompt you to complete the installation process.

### Installation via Moodle Plugin Interface
1. Download the SQLab plugin ZIP file.
2. Log in to your Moodle site as an administrator.
3. Navigate to **Site administration > Plugins > Install plugins**.
4. Upload the ZIP file through the provided interface and follow the on-screen instructions to complete the installation.

## Usage ##

Instructors can create assignments or tests directly within Moodle using SQLab. Each assignment can specify the SQL commands or scripts that students need to execute or correct. The plugin evaluates submissions automatically against the correct answers specified by the instructor.

## License ##

SQLab is released under the GNU General Public License v3.0, which allows you to redistribute and/or modify it under the terms of the GPL. The plugin is provided with NO WARRANTY, without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

See the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html) for more details.

## Contact ##

For more information, contact me:

- **Email**: [Jose.Fdez@alu.uclm.es](mailto:Jose.Fdez@alu.uclm.es)
