pipeline {
    agent any

    environment {
        SWARM_STACK_NAME = "app"
        DB_SERVICE = 'db'
        DB_USER = 'root'
        DB_PASSWORD = 'secret'
        DB_NAME = 'lena'
        FRONTEND_URL = 'http://192.168.0.1:8080'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Build Docker Images') {
            steps {
                script {
                    sh "docker build -f php.Dockerfile -t draktorio/crudback ."
                    sh "docker build -f mysql.Dockerfile -t draktorio/mysql ."
                }
            } 
        }
            
        stage('Deploy to Docker Swarm') {
            steps {
                script {
                    sh '''
                        if ! docker info | grep -q "Swarm: active"; then
                            docker swarm init || true
                        fi
                    '''
                    sh "docker stack deploy --with-registry-auth -c docker-compose.yaml ${SWARM_STACK_NAME}"
                }
            }
        } 
        
        stage('Run Tests') {
            steps {
                script {
                    echo "Ожидание запуска сервисов..."
                    sleep 30
                    
                    echo 'Проверка доступности фронта...'
                    sh """
                        if ! curl -fsS ${FRONTEND_URL}; then
                           echo 'Front недоступен'
                           exit 1
                        fi
                    """
                    
                    echo 'Получение ID контейнера базы данных...'
                    def dbContainerId = sh(
                        script: "docker ps --filter name=${SWARM_STACK_NAME}_${DB_SERVICE} --format '{{.ID}}'",
                        returnStdout: true
                    ).trim()
                    
                    if (!dbContainerId) {
                        error("Контейнер DB не найден")
                    }
                    
                    echo 'Подключение к MySQL и проверка таблиц...'
                    sh """
                        docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -e 'USE ${DB_NAME}; SHOW TABLES;'
                    """

                    // ==============================
                    // Проверка типа поля description
                    // ==============================
                    echo 'Проверка типа поля description...'

                    def fieldType = sh(
                        script: """
                            docker exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -D ${DB_NAME} -N -e \\
                            "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='${DB_NAME}' AND TABLE_NAME='records' AND COLUMN_NAME='description';"
                        """,
                        returnStdout: true
                    ).trim().toLowerCase()

                    echo "Тип поля description: ${fieldType}"

                    // Исправленная проверка
                    if (fieldType == 'varchar') {
                        error("ОШИБКА: Найдено недопустимое значение VARCHAR для поля description. Ожидается TEXT.")
                    } else if (fieldType == 'text') {
                        echo "УСПЕХ: Поле description имеет правильный тип TEXT. Проверка пройдена!"
                    } else {
                        error("НЕИЗВЕСТНЫЙ ТИП: Поле description имеет неожиданный тип: ${fieldType}")
                    }
                }
            }
        }  
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
        always {
            cleanWs()
        }
    }
}
